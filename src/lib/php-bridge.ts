import { spawn, spawnSync } from "child_process";
import { dirname, join } from "path";
import { fileURLToPath } from "url";
import { existsSync, readFileSync } from "fs";

/**
 * Minimum required PHP version for Silverstripe 6.
 */
const MIN_PHP_VERSION = "8.3.0";

/**
 * Common PHP binary locations to try when auto-detecting.
 */
const COMMON_PHP_PATHS = [
  // Windows - Laragon
  "C:/laragon/bin/php/php-8.3.22-Win32-vs16-x64/php.exe",
  "C:/laragon/bin/php/php-8.2.12-Win32-vs16-x64/php.exe",
  // Windows - XAMPP
  "C:/xampp/php/php.exe",
  // Windows - general
  "C:/php/php.exe",
  // Linux/Mac - Homebrew
  "/opt/homebrew/bin/php",
  "/usr/local/bin/php",
  // Linux - common locations
  "/usr/bin/php8.3",
  "/usr/bin/php8.2",
  "/usr/bin/php",
];

/**
 * Issue found during code analysis.
 */
export interface Issue {
  type: string;
  message: string;
  line: number;
  column: number | null;
  code: string | null;
  suggestion: string | null;
  docsUrl: string | null;
}

/**
 * Suggestion for code improvement (not an error).
 */
export interface Suggestion {
  type: string;
  message: string;
  line: number;
  column: number | null;
  code: string | null;
  replacement: string | null;
  docsUrl: string | null;
}

/**
 * Analysis result from PHP analyzer.
 */
export interface AnalysisResult {
  issues: Issue[];
  suggestions: Suggestion[];
  rerun: boolean;
}

/**
 * Input to PHP analyzer.
 */
export interface AnalysisInput {
  code: string;
  filename?: string;
  targetVersion?: string;
  enableAllPlugins?: boolean;
  config?: Record<string, unknown>;
  configPath?: string;
}

/**
 * Cached PHP binary path after resolution.
 */
let resolvedPhpBinary: string | null = null;

/**
 * Parse a PHP version string into comparable parts.
 */
function parsePhpVersion(versionString: string): number[] | null {
  const match = versionString.match(/^(\d+)\.(\d+)\.(\d+)/);
  if (!match) return null;
  return [parseInt(match[1], 10), parseInt(match[2], 10), parseInt(match[3], 10)];
}

/**
 * Compare two version arrays. Returns true if version >= minVersion.
 */
function isVersionSufficient(version: number[], minVersion: number[]): boolean {
  for (let i = 0; i < 3; i++) {
    if (version[i] > minVersion[i]) return true;
    if (version[i] < minVersion[i]) return false;
  }
  return true; // Equal
}

/**
 * Get the PHP version from a binary path.
 * Returns null if the binary doesn't exist or can't be executed.
 */
function getPhpVersion(phpPath: string): string | null {
  try {
    const result = spawnSync(phpPath, ["-v"], {
      encoding: "utf-8",
      timeout: 5000,
      windowsHide: true,
    });

    if (result.status !== 0 || !result.stdout) {
      return null;
    }

    // Parse version from "PHP 8.3.22 (cli) ..." format
    const match = result.stdout.match(/PHP (\d+\.\d+\.\d+)/);
    return match ? match[1] : null;
  } catch {
    return null;
  }
}

/**
 * Check if a PHP binary meets the minimum version requirement.
 */
function isPhpVersionSufficient(phpPath: string): boolean {
  const version = getPhpVersion(phpPath);
  if (!version) return false;

  const parsedVersion = parsePhpVersion(version);
  const parsedMinVersion = parsePhpVersion(MIN_PHP_VERSION);

  if (!parsedVersion || !parsedMinVersion) return false;
  return isVersionSufficient(parsedVersion, parsedMinVersion);
}

/**
 * Read PHP binary path from silverstripe-mcp.json config file.
 */
function getPhpBinaryFromConfig(configPath?: string): string | null {
  const pathsToTry = configPath
    ? [configPath]
    : ["silverstripe-mcp.json", "./silverstripe-mcp.json"];

  for (const path of pathsToTry) {
    try {
      if (existsSync(path)) {
        const content = readFileSync(path, "utf-8");
        const config = JSON.parse(content) as { phpBinary?: string };
        if (config.phpBinary) {
          return config.phpBinary;
        }
      }
    } catch {
      // Ignore parse errors
    }
  }
  return null;
}

/**
 * Resolve the PHP binary path using the following priority:
 * 1. Config file (silverstripe-mcp.json phpBinary)
 * 2. PHP_BINARY environment variable (if version >= 8.3)
 * 3. Auto-detect from common locations
 * 4. Fall back to "php" command
 *
 * @param configPath Optional path to silverstripe-mcp.json
 * @returns Resolved PHP binary path
 * @throws Error if no suitable PHP binary can be found
 */
export function resolvePhpBinary(configPath?: string): string {
  // Return cached value if available
  if (resolvedPhpBinary) {
    return resolvedPhpBinary;
  }

  // 1. Check config file
  const configPhp = getPhpBinaryFromConfig(configPath);
  if (configPhp) {
    if (existsSync(configPhp) && isPhpVersionSufficient(configPhp)) {
      resolvedPhpBinary = configPhp;
      return resolvedPhpBinary;
    }
    // Config specified a binary but it's invalid - warn but continue
    console.error(
      `Warning: phpBinary "${configPhp}" from config is invalid or below PHP ${MIN_PHP_VERSION}. Trying alternatives...`
    );
  }

  // 2. Check PHP_BINARY environment variable
  const envPhp = process.env.PHP_BINARY;
  if (envPhp) {
    if (isPhpVersionSufficient(envPhp)) {
      resolvedPhpBinary = envPhp;
      return resolvedPhpBinary;
    }
    const version = getPhpVersion(envPhp);
    console.error(
      `Warning: PHP_BINARY "${envPhp}" is PHP ${version || "unknown"}, but Silverstripe 6 requires PHP ${MIN_PHP_VERSION}+. Trying alternatives...`
    );
  }

  // 3. Auto-detect from common locations
  for (const phpPath of COMMON_PHP_PATHS) {
    if (existsSync(phpPath) && isPhpVersionSufficient(phpPath)) {
      resolvedPhpBinary = phpPath;
      return resolvedPhpBinary;
    }
  }

  // 4. Try system "php" command as last resort
  if (isPhpVersionSufficient("php")) {
    resolvedPhpBinary = "php";
    return resolvedPhpBinary;
  }

  // Nothing worked - throw helpful error
  const version = getPhpVersion("php");
  throw new Error(
    `No suitable PHP binary found. Silverstripe 6 requires PHP ${MIN_PHP_VERSION}+.\n` +
      (version ? `System PHP is ${version}.\n` : "") +
      `Solutions:\n` +
      `  1. Add "phpBinary" to silverstripe-mcp.json: {"phpBinary": "/path/to/php"}\n` +
      `  2. Set PHP_BINARY environment variable\n` +
      `  3. Install PHP ${MIN_PHP_VERSION}+ and add to PATH`
  );
}

/**
 * Clear the cached PHP binary path (useful for testing).
 */
export function clearPhpBinaryCache(): void {
  resolvedPhpBinary = null;
}

/**
 * Get the path to the PHP analyzer script.
 */
function getAnalyzerPath(): string {
  const __filename = fileURLToPath(import.meta.url);
  const __dirname = dirname(__filename);
  // From dist/lib/php-bridge.js, go up to project root, then to php/bin/analyze
  return join(__dirname, "..", "..", "php", "bin", "analyze");
}

/**
 * Call the PHP analyzer subprocess.
 *
 * @param input Analysis input containing code and options
 * @returns Analysis result with issues and suggestions
 */
export async function analyzeCode(input: AnalysisInput): Promise<AnalysisResult> {
  const analyzerPath = getAnalyzerPath();
  const jsonInput = JSON.stringify(input);

  return new Promise((resolve, reject) => {
    // Resolve PHP binary using config -> env var -> auto-detect -> fallback
    let phpBinary: string;
    try {
      phpBinary = resolvePhpBinary(input.configPath);
    } catch (error) {
      reject(error);
      return;
    }

    const child = spawn(phpBinary, [analyzerPath, jsonInput], {
      stdio: ["pipe", "pipe", "pipe"],
    });

    let stdout = "";
    let stderr = "";

    child.stdout.on("data", (data: Buffer) => {
      stdout += data.toString();
    });

    child.stderr.on("data", (data: Buffer) => {
      stderr += data.toString();
    });

    child.on("error", (error: Error) => {
      reject(new Error(`Failed to spawn PHP process: ${error.message}`));
    });

    child.on("close", (code: number | null) => {
      if (code !== 0 && code !== null) {
        // Try to parse error response from PHP
        try {
          const result = JSON.parse(stdout) as AnalysisResult;
          resolve(result);
          return;
        } catch {
          // If not valid JSON, return as error
          reject(new Error(`PHP process exited with code ${code}: ${stderr || stdout}`));
          return;
        }
      }

      try {
        const result = JSON.parse(stdout) as AnalysisResult;
        resolve(result);
      } catch {
        reject(new Error(`Invalid JSON response from PHP analyzer: ${stdout}`));
      }
    });
  });
}

/**
 * Format analysis result for MCP tool response.
 */
export function formatResult(result: AnalysisResult): string {
  if (result.issues.length === 0 && result.suggestions.length === 0) {
    return "No issues found. Code is compatible with Silverstripe 6.";
  }

  const parts: string[] = [];

  if (result.issues.length > 0) {
    parts.push(`Found ${result.issues.length} issue(s):\n`);

    for (const issue of result.issues) {
      parts.push(`- Line ${issue.line}: ${issue.message}`);
      if (issue.suggestion) {
        parts.push(`  Suggestion: ${issue.suggestion}`);
      }
      if (issue.docsUrl) {
        parts.push(`  Docs: ${issue.docsUrl}`);
      }
      parts.push("");
    }
  }

  if (result.suggestions.length > 0) {
    parts.push(`Suggestions:\n`);

    for (const suggestion of result.suggestions) {
      parts.push(`- Line ${suggestion.line}: ${suggestion.message}`);
      if (suggestion.replacement) {
        parts.push(`  Consider: ${suggestion.replacement}`);
      }
      parts.push("");
    }
  }

  if (result.rerun) {
    parts.push("Please fix the issues and run the validator again.");
  }

  return parts.join("\n");
}
