/**
 * `@joplin/turndown-plugin-gfm` ships no types. Each export is a turndown
 * plugin; `gfm` applies the other four.
 */
declare module "@joplin/turndown-plugin-gfm" {
    import type TurndownService from "turndown";

    export const gfm: TurndownService.Plugin;
    export const highlightedCodeBlock: TurndownService.Plugin;
    export const strikethrough: TurndownService.Plugin;
    export const tables: TurndownService.Plugin;
    export const taskListItems: TurndownService.Plugin;
}
