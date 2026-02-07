import { z } from "zod";
import { sections } from "./list-sections.js";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const CHANGELOG_URL = "https://docs.silverstripe.org/en/6/changelogs/6.0.0/";

/**
 * Input schema for get-documentation
 */
export const getDocumentationInputSchema = {
  section: z
    .string()
    .describe(
      'Section ID from list-sections (e.g., "renamed-classes", "cli-changes")'
    ),
};

/**
 * Tool definition for the get-documentation tool.
 */
export const getDocumentationTool = {
  name: "get-documentation",
  description:
    "Fetches Silverstripe 6 changelog documentation for a specific section. Call list-sections first to see available sections.",
  inputSchema: {
    type: "object" as const,
    properties: {
      section: {
        type: "string",
        description: "Section ID from list-sections",
      },
    },
    required: ["section"],
  },
};

/**
 * Input arguments for the get-documentation tool.
 */
export interface GetDocumentationArgs {
  section: string;
}

/**
 * Find docs directory by checking multiple possible locations.
 */
function findDocsDir(): string | null {
  // Possible locations relative to __dirname (dist/tools or src/tools)
  const possiblePaths = [
    path.join(__dirname, "..", "docs"), // dist/docs or src/docs
    path.join(__dirname, "..", "..", "src", "docs"), // ../src/docs from dist/tools
    path.join(__dirname, "..", "..", "dist", "docs"), // ../dist/docs from src/tools
  ];

  for (const dir of possiblePaths) {
    if (fs.existsSync(dir)) {
      return dir;
    }
  }
  return null;
}

/**
 * Handle a call to the get-documentation tool.
 *
 * @param args Tool arguments
 * @returns Tool result with documentation content
 */
export async function handleGetDocumentation(
  args: GetDocumentationArgs
): Promise<{ content: string; source: string }> {
  const docsDir = findDocsDir();

  // Handle "full" section - return complete changelog
  if (args.section === "full") {
    if (docsDir) {
      const filePath = path.join(docsDir, "changelog-6.0.0.md");
      if (fs.existsSync(filePath)) {
        const content = fs.readFileSync(filePath, "utf-8");
        return {
          content,
          source: CHANGELOG_URL,
        };
      }
    }
    return {
      content: `Full changelog not bundled. Fetch from: ${CHANGELOG_URL}`,
      source: CHANGELOG_URL,
    };
  }

  // Handle specific sections
  const section = sections.find((s) => s.id === args.section);

  if (!section) {
    const validSections = ["full", ...sections.map((s) => s.id)].join(", ");
    return {
      content: `Section "${args.section}" not found. Use list-sections to see available sections.\n\nValid sections: ${validSections}`,
      source: "error",
    };
  }

  // Read from bundled docs
  if (docsDir) {
    const filePath = path.join(docsDir, `${args.section}.md`);
    if (fs.existsSync(filePath)) {
      const content = fs.readFileSync(filePath, "utf-8");
      const footer = `\n\n---\n*Note: Use section "full" to get the complete changelog.*`;
      return {
        content: content + footer,
        source: `${CHANGELOG_URL}${section.anchor}`,
      };
    }
  }

  // Fallback to URL reference
  return {
    content: `Documentation for "${section.title}" is available at: ${CHANGELOG_URL}${section.anchor}\n\nThis section is not bundled. Please fetch the URL for full details.\n\n*Note: Use section "full" to get the complete changelog.*`,
    source: `${CHANGELOG_URL}${section.anchor}`,
  };
}
