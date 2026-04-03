# Board Value Proposition — Complete Plan

**Goal:** Make premium boards worth paying for by giving their jobs visible priority, and giving candidates a way to filter by board.

**What an employer paying for a premium board gets:**

1. **Featured badge** — All jobs on a premium board (credit_cost > 0) automatically get the "Featured" badge
2. **Priority sorting** — Featured jobs always appear FIRST in listings, before non-featured jobs
3. **Board badge** — Job cards show which board they belong to (e.g., "Tech Board", "Executive")
4. **Extended expiry** — Already configurable per board (expiry_days setting exists)

**What candidates see:**

1. Featured jobs with gold/amber highlight at the top of every search
2. Board name badge on job cards
3. Board filter chips on Find Jobs page (like job type/experience filters)

---

## Implementation Tasks

### Task 1: Add board data to job card (Free plugin)

**File:** `blocks/job-listings/render.php` (line ~155, job data array)

Add board info to each job's data:

```php
'board_id'   => (int) get_post_meta( $wcb_job_post->ID, '_wcb_board_id', true ),
'board_name' => '', // populated by Pro filter
```

Add filter so Pro can inject board name:
```php
$wcb_job_data = apply_filters( 'wcb_job_listing_data', $wcb_job_data, $wcb_job_post );
```

### Task 2: Pro hooks board name + auto-featured (Pro plugin)

**File:** `modules/boards/class-boards-pro-module.php`

Hook `wcb_job_listing_data` filter to:
1. Add `board_name` from the board post title
2. Force `featured = true` if board has `credit_cost > 0`

```php
add_filter( 'wcb_job_listing_data', function( $data, $post ) {
    $board_id = (int) ( $data['board_id'] ?? 0 );
    if ( $board_id > 0 ) {
        $board = get_post( $board_id );
        $data['board_name'] = $board ? $board->post_title : '';
        
        // Premium boards auto-feature their jobs
        $settings = (new BoardSettings())->get( $board_id );
        if ( (int) $settings['credit_cost'] > 0 ) {
            $data['featured'] = true;
        }
    }
    return $data;
}, 10, 2 );
```

### Task 3: Sort featured jobs first (Free plugin)

**File:** `blocks/job-listings/render.php` (query args)

The job query already exists. Modify it to sort featured jobs first:

```php
'meta_key'  => '_wcb_featured',
'orderby'   => array(
    'meta_value' => 'DESC',  // featured (1) first
    'date'       => 'DESC',  // then by date
),
```

Also in the REST API jobs endpoint, add the same sort for AJAX pagination.

### Task 4: Board badge on job card (Free plugin)

**File:** `blocks/job-listings/render.php` (line ~427, badges section)

Add board badge after the featured badge:

```html
<span class="wcb-cbadge wcb-cbadge--board" role="status" 
    data-wp-class--wcb-shown="context.job.board_name" 
    data-wp-text="context.job.board_name"></span>
```

**File:** `blocks/job-listings/style.css`

Add board badge styling:
```css
.wcb-cbadge--board {
    background: var(--wcb-accent-light, #eff6ff);
    color: var(--wcb-accent, #2563eb);
}
```

### Task 5: Board filter on Find Jobs page (Free plugin)

**File:** `blocks/job-listings/render.php`

Add board filter chips (like job type chips). Query published boards:
```php
$wcb_boards = get_posts(['post_type' => 'wcb_board', 'post_status' => 'publish', 'numberposts' => -1]);
```

Render as chip buttons in the filter bar. The view.js needs to support board filtering in the API call.

---

## Verification

After implementation:
1. Create a board "Premium Tech" with credit_cost = 5
2. Post a job on that board
3. Verify: job card shows "Featured" badge + "Premium Tech" board badge
4. Verify: that job appears FIRST in listings (before free jobs)
5. Verify: board filter chip appears on Find Jobs page
6. Verify: clicking the chip filters to only that board's jobs
