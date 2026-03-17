# QA Fixes — Free Plugin Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the two confirmed accessibility bugs in the admin modal found during the QA audit.

**Architecture:** Both fixes are in `assets/js/admin.js` inside the `openModal()` function. The title element needs a stable `id` so the dialog can reference it via `aria-labelledby`. The overlay needs a `keydown` listener to close on Escape.

**Tech Stack:** Vanilla JS (no build step — this file is served as-is via `wp_enqueue_script`).

---

## File Map

| Action | Path |
|--------|------|
| Modify | `assets/js/admin.js` |

---

## Task 1: Fix admin modal accessibility

**Files:**
- Modify: `assets/js/admin.js`

Two bugs in `openModal()`:

1. `role="dialog"` element has no `aria-labelledby` — screen readers can't announce the dialog title.
2. No `keydown` listener — pressing Escape does not close the modal (keyboard trap).

**Current code (lines 25–37, abbreviated):**

```js
var box = document.createElement( 'div' );
box.className = 'wcb-modal';
box.setAttribute( 'role', 'dialog' );
box.setAttribute( 'aria-modal', 'true' );

var title = document.createElement( 'h2' );
title.className = 'wcb-modal-title';
title.textContent = opts.title || '';
```

**Target code:**

```js
var box = document.createElement( 'div' );
box.className = 'wcb-modal';
box.setAttribute( 'role', 'dialog' );
box.setAttribute( 'aria-modal', 'true' );
box.setAttribute( 'aria-labelledby', 'wcb-modal-title' );

var title = document.createElement( 'h2' );
title.id = 'wcb-modal-title';
title.className = 'wcb-modal-title';
title.textContent = opts.title || '';
```

And add the Escape key listener alongside the existing `cancelBtn` click handler:

```js
function handleKey( e ) {
    if ( e.key === 'Escape' ) {
        document.removeEventListener( 'keydown', handleKey );
        close();
        reject();
    }
}
document.addEventListener( 'keydown', handleKey );

cancelBtn.addEventListener( 'click', function () {
    document.removeEventListener( 'keydown', handleKey );
    close();
    reject();
} );
```

Also update `confirmBtn` click to remove the listener:

```js
confirmBtn.addEventListener( 'click', function () {
    document.removeEventListener( 'keydown', handleKey );
    close();
    resolve( opts.withReason ? ( textarea ? textarea.value.trim() : '' ) : true );
} );
```

And `overlay` click:

```js
overlay.addEventListener( 'click', function ( e ) {
    if ( e.target === overlay ) {
        document.removeEventListener( 'keydown', handleKey );
        close();
        reject();
    }
} );
```

- [ ] **Step 1: Apply the aria-labelledby + title id change**

Edit `assets/js/admin.js`: in `openModal`, change:

```js
		box.setAttribute( 'aria-modal', 'true' );

		var title = document.createElement( 'h2' );
		title.className = 'wcb-modal-title';
```

To:

```js
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-labelledby', 'wcb-modal-title' );

		var title = document.createElement( 'h2' );
		title.id = 'wcb-modal-title';
		title.className = 'wcb-modal-title';
```

- [ ] **Step 2: Add the Escape key listener**

After `function close() { ... }`, add the `handleKey` function and attach it as a listener. Then update the three existing `removeEventListener` calls into `cancelBtn`, `overlay`, and `confirmBtn` handlers.

Full updated block (replacing lines 74–93):

```js
		function close() {
			document.body.removeChild( overlay );
		}

		function handleKey( e ) {
			if ( e.key === 'Escape' ) {
				document.removeEventListener( 'keydown', handleKey );
				close();
				reject();
			}
		}
		document.addEventListener( 'keydown', handleKey );

		cancelBtn.addEventListener( 'click', function () {
			document.removeEventListener( 'keydown', handleKey );
			close();
			reject();
		} );

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				document.removeEventListener( 'keydown', handleKey );
				close();
				reject();
			}
		} );

		confirmBtn.addEventListener( 'click', function () {
			document.removeEventListener( 'keydown', handleKey );
			close();
			resolve( opts.withReason ? ( textarea ? textarea.value.trim() : '' ) : true );
		} );
```

- [ ] **Step 3: Commit**

```bash
cd /Users/varundubey/Local\ Sites/job-portal/app/public/wp-content/plugins/wp-career-board
git add assets/js/admin.js
git commit -m "fix(wcb): QA — admin modal aria-labelledby and Escape key handler"
```

---

## Verification

1. Open the Applications admin page.
2. Click Approve or Reject on any job.
3. Confirm: pressing Escape closes the modal.
4. Inspect the rendered HTML: `<div role="dialog" aria-labelledby="wcb-modal-title">` and `<h2 id="wcb-modal-title">`.
5. Run a screen reader (VoiceOver/NVDA) and confirm it announces the dialog title on open.
