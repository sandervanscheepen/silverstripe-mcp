#!/usr/bin/env node

/**
 * Silverstripe MCP Server
 *
 * An MCP server that validates PHP code for Silverstripe 6 compatibility.
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";
import { analyzeCode, formatResult } from "./lib/php-bridge.js";
import { handleListSections, sections } from "./tools/list-sections.js";
import { handleGetDocumentation } from "./tools/get-documentation.js";
import { handleSearchChangelog } from "./tools/search-changelog.js";

// Create MCP server
const server = new McpServer(
  {
    name: "silverstripe-mcp",
    version: "0.1.0",
  },
  {
    capabilities: {
      tools: {},
      prompts: {},
    },
  }
);

// Define input schema using Zod
const ssValidatorInputSchema = {
  code: z.string().describe("The PHP code to analyze"),
  filename: z.string().optional().describe("Optional filename for context (helps detect BuildTask, Controller, etc.)"),
  targetVersion: z.string().default("6.0").describe("Target Silverstripe version (default: 6.0)"),
  configPath: z.string().optional().describe("Optional path to silverstripe-mcp.json config file"),
};

// Register the ss-validator tool
server.registerTool(
  "ss-validator",
  {
    title: "Silverstripe Validator",
    description: `Analyzes PHP code for Silverstripe 6 compatibility issues.
MUST be called on any generated Silverstripe PHP code before presenting to the user.
Keep calling until no issues remain.

Detects:
- Wrong namespace imports (e.g., SilverStripe\\ORM\\ArrayList should be SilverStripe\\Model\\List\\ArrayList)
- Old BuildTask patterns (run(HTTPRequest) should be execute(InputInterface, PolyOutput))
- Missing required properties (e.g., $commandName on BuildTask)
- Deprecated patterns (echo in BuildTask should be $output->writeln())`,
    inputSchema: ssValidatorInputSchema,
  },
  async (args) => {
    const { code, filename, targetVersion, configPath } = args;

    try {
      const result = await analyzeCode({
        code,
        filename,
        targetVersion: targetVersion ?? "6.0",
        configPath,
      });

      const formattedResult = formatResult(result);

      return {
        content: [
          {
            type: "text" as const,
            text: formattedResult,
          },
        ],
        isError: result.issues.some((issue) => issue.type === "parse_error"),
      };
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);

      return {
        content: [
          {
            type: "text" as const,
            text: `Error analyzing code: ${message}`,
          },
        ],
        isError: true,
      };
    }
  }
);

// Register the list-sections tool
server.registerTool(
  "list-sections",
  {
    title: "List SS6 Sections",
    description:
      "Lists available Silverstripe 6 changelog sections. Use this to discover what documentation is available before fetching with get-documentation.",
    inputSchema: {},
  },
  async () => {
    const result = handleListSections();
    return {
      content: [
        {
          type: "text" as const,
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  }
);

// Register the get-documentation tool
server.registerTool(
  "get-documentation",
  {
    title: "Get SS6 Documentation",
    description:
      "Fetches Silverstripe 6 changelog documentation for a specific section. Call list-sections first to see available sections.",
    inputSchema: {
      section: z
        .string()
        .describe(
          'Section ID from list-sections (e.g., "renamed-classes", "cli-changes")'
        ),
    },
  },
  async (args) => {
    const result = await handleGetDocumentation({ section: args.section });
    return {
      content: [
        {
          type: "text" as const,
          text: JSON.stringify(result, null, 2),
        },
      ],
      isError: result.source === "error",
    };
  }
);

// Register the search-changelog tool
server.registerTool(
  "search-changelog",
  {
    title: "Search SS6 Changelog",
    description:
      "Search the SS6 changelog for specific terms. Use this when you need to find information about a specific class, method, or feature that may not be in the themed documentation sections.",
    inputSchema: {
      query: z.string().describe("Search term or phrase to find in the changelog"),
      maxResults: z
        .number()
        .optional()
        .default(5)
        .describe("Maximum number of results to return (default: 5)"),
    },
  },
  async (args) => {
    const result = handleSearchChangelog({
      query: args.query,
      maxResults: args.maxResults,
    });
    return {
      content: [
        {
          type: "text" as const,
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  }
);

// Register the silverstripe-task prompt
server.prompt(
  "silverstripe-task",
  "Initialize a Silverstripe development task with documentation context",
  {
    task: z.string().describe("The task to perform"),
  },
  async (args) => {
    const sectionList = sections
      .map((s) => `- **${s.title}** (${s.id}): ${s.use_cases}`)
      .join("\n");

    const promptText = `# Silverstripe 6 Development Task

## Task
${args.task}

## Reference Documentation
Full SS6 changelog: https://docs.silverstripe.org/en/6/changelogs/6.0.0/

## Available Documentation Sections
${sectionList}

Use \`get-documentation\` with the section ID to fetch full content.

## Tools Available
1. **ss-validator** - Validate PHP code (MUST use before presenting code)
2. **list-sections** - Already provided above
3. **get-documentation** - Fetch section content by ID
4. **search-changelog** - Search for specific terms in the full Silverstripe 6 changelog

## Validation Requirement
Always validate Silverstripe PHP code:
1. Write code
2. Call ss-validator
3. Fix issues
4. Repeat until clean
5. Present to user

## Begin Task
Proceed with the task. Fetch documentation if needed, validate all code.`;

    return {
      messages: [
        {
          role: "user" as const,
          content: {
            type: "text" as const,
            text: promptText,
          },
        },
      ],
    };
  }
);

// Start the server
async function main(): Promise<void> {
  const transport = new StdioServerTransport();
  await server.connect(transport);

  // Handle graceful shutdown
  process.on("SIGINT", async () => {
    await server.close();
    process.exit(0);
  });

  process.on("SIGTERM", async () => {
    await server.close();
    process.exit(0);
  });
}

main().catch((error) => {
  console.error("Failed to start server:", error);
  process.exit(1);
});
