# ADR 0015: Menu, sticky main buttons and toast messages

Status: accepted (2026-10-20)

## Decision
**Menu**
- Clicking any menu item closes the menu; this matters mainly for the active page item, where the address does not change and the menu would not disappear on its own.
- The menu close button stays blue like the round party button (as in the old prototype), not grey.
- The Parta (Party) section contains "Statistiky" (Statistics) and then "Spravovat" (Manage) (party detail).
- All hover effects (menu, lists, icons, buttons) have the same light 0.15 s animation as the buttons.

**Main buttons (`Actions`)**
- The bar is `position: sticky; bottom: 0` with a transparent white blurred background. On desktop it therefore sticks only when the page is longer than the screen; on mobile (up to 576 px) the page fills the screen and the bar is always at the bottom edge, even on a short page.
- Sticky holds only within its parent, so `Actions` is always directly in the page (`.page`) or in a form, not in a small block just around itself. A button inside content (e.g. "Upravit" (Edit) in an expanded play) is `<Actions inline>` and does not stick.

**Error messages**
- API errors and form validation messages are shown as toasts (`ToastProvider`, `ErrorMessage`, `useToasts().showError`): red (`#da2736`, 90 %) with `backdrop-filter` like the menu, the same height as the header with the logo (5 rem), stacked from top to bottom, several at once, each with a cross in the place of the party button. They disappear after 6 s (+1 s per detail); at the bottom each has a countdown bar, which also separates the messages stacked below each other. On mouse hover both the countdown and the bar pause, and the same text is not stacked twice. A closed message first collapses to zero height (grid `1fr → 0fr`), so the messages below it slide up smoothly.
- `ErrorMessage` renders nothing by itself; the `inline` variant remained for pages that have no other content because of the error (the game, party or play detail did not load).

**Game list filter (popup instead of select)**
- Sorting and Sessions in the filter panel are buttons with the current choice (`SelectPopup`, modeled on `common-select` from the old prototype). A click opens a popup across the whole app width that covers the content (white with blur), with the name of the choice and a search at the top that has focus immediately, and below it a list of options with a toggle. The search ignores case and diacritics; the selected option stays visible (dimmed) even outside the results and "Všechny" (All, no filter) is always first.
- Closing: choosing an option, Esc, or the cross, which stands in the place of the round party button (which the popup covers, as it turns into a close button in the prototype). Focus returns to the button.
- Other `<select>`s (game scoring type, game player, player character) remain native.

**Tooltips of icon buttons**
- A single global layer `TooltipLayer` (in `App`) tracks mouse hover and keyboard focus and shows a custom tooltip for every button or link that contains only an icon (the text is taken from `aria-label`, so icon buttons need no special marking and new ones get tooltips by themselves). A `data-tooltip` text overrides it (an empty one turns it off) and also works on a button with text; the native `title` is not used.
- A dark label with an arrow above the element (below it when there is no room above), in a portal above everything else, kept horizontally within the window; it appears after 350 ms (immediately on keyboard focus) with a light animation and disappears on mouse-out, click, scroll, Esc. After a mouse click the tooltip does not stay hanging, and on touch devices (`hover: none`) it is not shown.
