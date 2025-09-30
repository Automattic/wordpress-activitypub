# ActivityPub Modal Component

A reusable modal dialog for WordPress blocks, built using the WordPress Interactivity API.

## Overview

This modal component provides a consistent, accessible way to display dialogs in different blocks within the ActivityPub plugin. It supports two styles—compact (like a popover) and full-size—and includes features like focus trapping, keyboard controls, and click-outside-to-close behavior.

## Key Features

- Two display modes: compact (popover-style) and full-size.
- Built-in accessibility support (focus management, ARIA roles).
- Keyboard navigation (e.g., Esc to close).
- Dismiss by clicking outside the modal.
- Optional title and customizable content.
- Uses context to safely manage modal state in each block.

## How It Works

The modal is built from three main parts:

1. **JavaScript Controller**  
   Manages the modal’s open/close state and behavior. It integrates with the WordPress Interactivity API using your block’s namespace.

2. **PHP Rendering**  
   Renders the modal’s HTML using `Blocks::render_modal()`. You control what content appears in the modal and whether it’s compact or full-size.

3. **SCSS Styling**  
   Handles the modal’s appearance and animations for both variants.

## Getting Started

### 1. Import the Modal Controller

In your block’s JS file (e.g., `view.js`):

```js
import { createModalStore } from '../shared/modal';
```

### 2. Initialize the Controller

Pass your block's namespace to set up modal state handling:

```js
createModalStore('activitypub/your-block-name');
```

#### Why Namespace Matters

#### Why the Namespace Matters

The namespace you pass to `createModalStore()` should match your block’s namespace because the WordPress Interactivity API merges all stores that share the same namespace.

This means:

* The modal’s actions and callbacks are added directly to your block’s existing store.
* You can access and manage modal state (`context.modal`) as part of your block’s overall context.
* There’s no need to manage a separate modal store—everything is scoped and available within your block’s interactive logic.

Using a consistent namespace ensures the modal integrates cleanly into your block's behavior without conflicts or extra configuration.

### 3. Set Modal Context in PHP

In your block’s `render.php`, define a `modal` key in the context array:

```php
$context = array(
    'blockId' => $block_id,
    'modal'   => array(
        'isOpen'    => false,
        'isCompact' => true, // Use false for a full-size modal.
        // Add any other context data you need.
    ),
);

$wrapper_attributes = get_block_wrapper_attributes(
    array(
        'id'                  => $block_id,
        'data-wp-interactive' => 'activitypub/your-block-name',
        'data-wp-context'     => wp_json_encode( $context ),
    )
);
```

### 4. Render the Modal

Call `Blocks::render_modal()` to output the modal HTML:

```php
$modal_content = '
    <div class="your-modal-content">
        <!-- Your modal content here -->
    </div>
';

Blocks::render_modal(
    array(
        'is_compact' => true, // false for full-size.
        'title'      => __( 'Your Modal Title', 'activitypub' ), // Optional; used in full-size modals.
        'content'    => $modal_content,
    )
);
```

### 5. Add a Trigger Button

Use the Interactivity API to connect a button that opens the modal:

```php
$button = '<button class="wp-element-button" data-wp-on--click="actions.toggleModal">Open Modal</button>';
```

## Built-in Actions

The modal controller provides these actions automatically in your namespace:

* `actions.openModal()` – Opens the modal.
* `actions.closeModal()` – Closes the modal.
* `actions.toggleModal()` – Toggles modal open/closed.

## Customize Modal Behavior

You can hook into modal open/close events by registering callbacks in your store:

```js
const { callbacks } = store( 'activitypub/your-block-name', {
    callbacks: {
        onModalOpen() {
            // Run custom logic when the modal opens.
        },
        onModalClose() {
            // Run custom logic when the modal closes.
        },
    }
});
```

## Styling

Default modal styles are in `style.scss`, including animations and responsive layout for both modal sizes. Import the styles into your block:

```scss
@import '../shared/modal/style';

// Add any block-specific styles below
```

## Accessibility

The modal is built with accessibility in mind:

* ARIA roles for dialogs
* Automatic focus trapping inside the modal
* Keyboard support (Escape key, tab navigation)

## Examples

### Compact Modal (Popover Style)

See implementation in:

* `src/reactions/view.js`
* `src/reactions/render.php`

### Full-Size Modal

See implementation in:

* `src/follow-me/view.js`
* `src/follow-me/render.php`
