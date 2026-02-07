import { z } from "zod";

/**
 * Curated sections from the Silverstripe 6 changelog.
 * Source: https://docs.silverstripe.org/en/6/changelogs/6.0.0/
 */
export const sections = [
  {
    id: "full",
    title: "Complete 6.0.0 Changelog",
    use_cases:
      "full changelog, complete reference, all changes, overview, breaking changes, upgrade guide",
    anchor: "",
  },
  {
    id: "renamed-classes",
    title: "Renamed and Moved Classes",
    use_cases:
      "wrong imports, namespace errors, class not found, ArrayList, ArrayData, ViewableData, ModelData",
    anchor: "#renamed-classes",
  },
  {
    id: "cli-changes",
    title: "Changes to sake, BuildTask, and CLI",
    use_cases:
      "BuildTask, PolyCommand, run method, execute method, console commands, sake",
    anchor: "#cli-changes",
  },
  {
    id: "hooks-changes",
    title: "Extension Hook Changes (Renamed and Protected)",
    use_cases:
      "extension hooks, onBeforeWrite, onAfterWrite, updateCMSFields, visibility, DataExtension, Extension",
    anchor: "#hooks-protected",
  },
  {
    id: "dbfield-validation",
    title: "Validation Added to DBFields",
    use_cases: "DBField, validation, type errors, Varchar, Int, Boolean, DBEmail, DBUrl, DBIp",
    anchor: "#dbfield-validation",
  },
  {
    id: "formfield-changes",
    title: "FormField Value and Validation Changes",
    use_cases: "FormField, getValue, getFormattedValue, validate, RequiredFieldsValidator, scaffolding",
    anchor: "#formfield-changes",
  },
  {
    id: "view-layer",
    title: "Changes to Templating/View Layer",
    use_cases: "templates, ViewableData, ModelData, rendering, TemplateEngine, ViewLayerData",
    anchor: "#view-layer",
  },
  {
    id: "graphql-removed",
    title: "GraphQL Removed from CMS",
    use_cases: "GraphQL, API, admin interface",
    anchor: "#graphql-removed",
  },
  {
    id: "dependency-changes",
    title: "Dependency Changes",
    use_cases: "PHP version, Symfony, intervention/image, PHPUnit",
    anchor: "#dependency-changes",
  },
  {
    id: "api-changes",
    title: "Full API Changes",
    use_cases: "method signatures, removed methods, changed parameters",
    anchor: "#api-changes",
  },
] as const;

export type Section = (typeof sections)[number];

/**
 * Input schema for list-sections (no input required)
 */
export const listSectionsInputSchema = {};

/**
 * Tool definition for the list-sections tool.
 */
export const listSectionsTool = {
  name: "list-sections",
  description:
    "Lists available Silverstripe 6 changelog sections. Use this to discover what documentation is available before fetching with get-documentation.",
  inputSchema: {
    type: "object" as const,
    properties: {},
  },
};

const CHANGELOG_URL = "https://docs.silverstripe.org/en/6/changelogs/6.0.0/";

/**
 * Handle a call to the list-sections tool.
 *
 * @returns Tool result with section index
 */
export function handleListSections(): {
  sections: typeof sections;
  source: string;
} {
  return {
    sections,
    source: CHANGELOG_URL,
  };
}
