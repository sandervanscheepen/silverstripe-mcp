import { z } from "zod";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const CHANGELOG_URL = "https://docs.silverstripe.org/en/6/changelogs/6.0.0/";

/**
 * Input schema for search-changelog
 */
export const searchChangelogInputSchema = {
  query: z.string().describe("Search term or phrase to find in the changelog"),
  maxResults: z
    .number()
    .optional()
    .default(5)
    .describe("Maximum number of results to return (default: 5)"),
};

/**
 * Tool definition for the search-changelog tool.
 */
export const searchChangelogTool = {
  name: "search-changelog",
  description:
    "Search the SS6 changelog for specific terms. Use this when you need to find information about a specific class, method, or feature that may not be in the themed documentation sections.",
  inputSchema: {
    type: "object" as const,
    properties: {
      query: {
        type: "string",
        description: "Search term or phrase",
      },
      maxResults: {
        type: "number",
        description: "Maximum results to return (default: 5)",
      },
    },
    required: ["query"],
  },
};

/**
 * Input arguments for the search-changelog tool.
 */
export interface SearchChangelogArgs {
  query: string;
  maxResults?: number;
}

/**
 * A single search result with context.
 */
interface SearchResult {
  heading: string;
  anchor: string;
  excerpt: string;
  lineNumber: number;
}

/**
 * Find the changelog file by checking multiple possible locations.
 */
function findChangelogPath(): string | null {
  // Possible locations relative to __dirname (dist/tools or src/tools)
  const possiblePaths = [
    path.join(__dirname, "..", "docs", "changelog-6.0.0.md"), // dist/docs or src/docs
    path.join(__dirname, "..", "..", "src", "docs", "changelog-6.0.0.md"), // ../src/docs from dist/tools
    path.join(__dirname, "..", "..", "dist", "docs", "changelog-6.0.0.md"), // ../dist/docs from src/tools
  ];

  for (const p of possiblePaths) {
    if (fs.existsSync(p)) {
      return p;
    }
  }
  return null;
}

/**
 * Handle a call to the search-changelog tool.
 *
 * @param args Tool arguments
 * @returns Tool result with search results
 */
export function handleSearchChangelog(args: SearchChangelogArgs): {
  results: SearchResult[];
  query: string;
  totalMatches: number;
  source: string;
} {
  const changelogPath = findChangelogPath();

  if (!changelogPath) {
    return {
      results: [],
      query: args.query,
      totalMatches: 0,
      source: `Changelog file not found. Please fetch from: ${CHANGELOG_URL}`,
    };
  }

  const changelog = fs.readFileSync(changelogPath, "utf-8");
  const lines = changelog.split("\n");

  const query = args.query.toLowerCase();
  const maxResults = args.maxResults || 5;
  const results: SearchResult[] = [];
  let totalMatches = 0;

  let currentHeading = "";
  let currentAnchor = "";

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];

    // Track current heading for context (supports {#anchor} syntax)
    const headingMatch = line.match(/^(#{1,3})\s+(.+?)(?:\s+\{#([\w-]+)\})?$/);
    if (headingMatch) {
      currentHeading = headingMatch[2];
      currentAnchor = headingMatch[3] || "";
    }

    // Check if line contains query (case-insensitive)
    if (line.toLowerCase().includes(query)) {
      totalMatches++;

      // Only add to results if we haven't hit the limit
      if (results.length < maxResults) {
        // Get surrounding context (2 lines before and after)
        const start = Math.max(0, i - 2);
        const end = Math.min(lines.length - 1, i + 2);
        const excerpt = lines.slice(start, end + 1).join("\n");

        results.push({
          heading: currentHeading,
          anchor: currentAnchor,
          excerpt: excerpt.trim(),
          lineNumber: i + 1,
        });
      }
    }
  }

  return {
    results,
    query: args.query,
    totalMatches,
    source: CHANGELOG_URL,
  };
}
