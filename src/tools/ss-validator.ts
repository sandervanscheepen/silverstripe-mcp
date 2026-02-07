import { analyzeCode, formatResult, type AnalysisInput, type AnalysisResult } from "../lib/php-bridge.js";

/**
 * Tool definition for the ss-validator tool.
 */
export const ssValidatorTool = {
  name: "ss-validator",
  description: `Analyzes PHP code for Silverstripe 6 compatibility issues.
MUST be called on any generated Silverstripe PHP code before presenting to the user.
Keep calling until no issues remain.

Detects:
- Wrong namespace imports (e.g., SilverStripe\\ORM\\ArrayList should be SilverStripe\\Model\\List\\ArrayList)
- Old BuildTask patterns (run(HTTPRequest) should be execute(InputInterface, PolyOutput))
- Missing required properties (e.g., $commandName on BuildTask)
- Deprecated patterns (echo in BuildTask should be $output->writeln())

Plugins auto-enable based on code context (e.g., Extension hooks only checked when extending Extension).
Use enableAllPlugins: true to force all checks.`,
  inputSchema: {
    type: "object" as const,
    properties: {
      code: {
        type: "string",
        description: "The PHP code to analyze",
      },
      filename: {
        type: "string",
        description: "Optional filename for context (helps detect BuildTask, Controller, etc.)",
      },
      targetVersion: {
        type: "string",
        default: "6.0",
        description: "Target Silverstripe version (default: 6.0)",
      },
      enableAllPlugins: {
        type: "boolean",
        default: false,
        description: "Force enable all plugins including context-specific ones (default: false)",
      },
      configPath: {
        type: "string",
        description: "Optional path to silverstripe-mcp.json config file",
      },
    },
    required: ["code"],
  },
};

/**
 * Input arguments for the ss-validator tool.
 */
export interface SsValidatorArgs {
  code: string;
  filename?: string;
  targetVersion?: string;
  enableAllPlugins?: boolean;
  configPath?: string;
}

/**
 * Handle a call to the ss-validator tool.
 *
 * @param args Tool arguments
 * @returns Tool result with formatted issues
 */
export async function handleSsValidator(args: SsValidatorArgs): Promise<{
  content: Array<{ type: "text"; text: string }>;
  isError?: boolean;
}> {
  const input: AnalysisInput = {
    code: args.code,
    filename: args.filename,
    targetVersion: args.targetVersion ?? "6.0",
    enableAllPlugins: args.enableAllPlugins ?? false,
    configPath: args.configPath,
  };

  try {
    const result: AnalysisResult = await analyzeCode(input);
    const formattedResult = formatResult(result);

    return {
      content: [
        {
          type: "text",
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
          type: "text",
          text: `Error analyzing code: ${message}`,
        },
      ],
      isError: true,
    };
  }
}
