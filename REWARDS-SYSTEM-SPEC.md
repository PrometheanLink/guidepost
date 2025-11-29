# Sojourn Rewards - Credit System Specification

**Status:** Awaiting Client Approval
**Client:** Kim Benedict (Sojourn Coaching)
**Date:** November 29, 2025
**Landing Page Preview:** `assets/pages/rewards-program.html`

---

## Executive Summary

A loyalty/rewards program that incentivizes client engagement through a credit-based system. Clients earn credits for positive actions (referrals, session completion, reviews) and redeem them for valuable rewards (discounts, free sessions, retreat access).

**Key Principles:**
- Credits never expire
- Simple to understand
- Rewards feel meaningful
- Drives referrals (primary business goal)

---

## Credit Earning Structure

| Action | Credits | Frequency | Trigger |
|--------|---------|-----------|---------|
| **Refer a friend who books** | +100 | Per referral | When referred client completes first booking |
| **Complete 5 sessions** | +25 | Repeatable | Every 5th completed appointment |
| **Leave Google review** | +50 | One-time | Admin manually awards after verification |
| **6-month anniversary** | +50 | One-time | Auto-triggered on anniversary date |
| **10 sessions milestone** | +50 | One-time | Auto-triggered on 10th completed session |

### Earning Rules

1. **Referrals:**
   - Each customer gets a unique referral code/link
   - Referred person must complete a booking (not just sign up)
   - Both referrer AND referee get 100 credits
   - No limit on referral earnings

2. **Session Milestones:**
   - Only counts completed appointments (not cancelled/no-show)
   - 5-session bonus repeats: 5, 10, 15, 20, etc.
   - 10-session milestone is one-time bonus on top of 5-session bonus

3. **Google Reviews:**
   - Admin verifies review exists before awarding
   - One-time award per customer
   - Could expand to other platforms later (Yelp, Facebook)

4. **Anniversary:**
   - Calculated from first completed appointment
   - Auto-triggered via scheduled task/cron

---

## Credit Redemption Structure

| Reward | Cost | Type | Description |
|--------|------|------|-------------|
| **Resource Bundle** | 100 credits | Unlock | Access to exclusive workbooks, templates, guided meditations |
| **$50 Session Credit** | 250 credits | Discount | Applies as discount at checkout for any service |
| **Free Discovery Session** | 500 credits | Booking | Creates a $0 appointment OR giftable to another person |
| **Retreat Invitation** | 1000 credits | Access | Priority invitation + special pricing for retreats |

### Redemption Rules

1. **Resource Bundle:**
   - Unlocks download access in customer dashboard
   - One-time redemption (keeps access forever)
   - Could be tiered later (Bundle 1, Bundle 2, etc.)

2. **$50 Session Credit:**
   - Applied as WooCommerce discount at checkout
   - Can only use one per transaction
   - If service costs less than $50, no change given
   - Repeatable redemption

3. **Free Discovery Session:**
   - Can book for self OR enter a friend's email
   - Creates appointment with $0 price
   - Bypasses WooCommerce checkout
   - Friend receives email invitation to claim

4. **Retreat Invitation:**
   - Adds customer to "Retreat VIP" list
   - Triggers email notification to admin
   - Customer receives priority booking link when retreat announced
   - Includes discount code (amount TBD by Kim)

---

## User Experience

### Customer Dashboard
- Credit balance prominently displayed
- Transaction history (earned/redeemed)
- Available rewards with "Redeem" buttons
- Referral link/code with copy button
- Progress toward next earning milestone

### Earning Notifications
- Toast/popup when credits earned
- Email notification for significant earnings (referral success)
- Dashboard badge for new credits

### Redemption Flow
1. Customer clicks "Redeem" on reward
2. Confirmation modal explains what they'll receive
3. Credits deducted immediately
4. Reward fulfilled (download unlocked, discount code generated, etc.)
5. Confirmation email sent

---

## Admin Features

### Dashboard Widget
- Total credits in circulation
- Credits earned this month
- Credits redeemed this month
- Top referrers leaderboard

### Manual Adjustments
- Add/subtract credits with reason (already exists in GuidePost)
- Award one-time bonuses (birthday, special recognition)
- Bulk award for promotions

### Configuration
- Enable/disable specific earning actions
- Adjust credit amounts per action
- Enable/disable specific rewards
- Adjust reward costs
- Set resource bundle download URL

---

## Technical Implementation

### Recommended: Sister Plugin Architecture

**Plugin Name:** GuidePost Rewards
**Dependency:** Requires GuidePost plugin active

```
guidepost-rewards/
├── guidepost-rewards.php          # Main plugin file
├── includes/
│   ├── class-rewards-core.php     # Core credit functions
│   ├── class-rewards-earning.php  # Earning triggers & logic
│   ├── class-rewards-redeem.php   # Redemption handlers
│   ├── class-rewards-referral.php # Referral system
│   └── class-rewards-admin.php    # Admin settings & UI
├── assets/
│   ├── css/
│   ├── js/
│   └── pages/
│       └── rewards-program.html   # Landing page template
└── templates/
    └── customer-rewards.php       # Dashboard widget template
```

### Why Sister Plugin?

| Benefit | Description |
|---------|-------------|
| **Modular** | Enable/disable per client without touching core |
| **Configurable** | Each client can customize earning/redemption rules |
| **Maintainable** | Update rewards system independently |
| **Sellable** | Can offer as premium add-on |
| **Clean** | Core GuidePost stays focused on bookings |

### Hook Integration with GuidePost

The sister plugin hooks into GuidePost events:

```php
// Earning Hooks (listen to GuidePost actions)
add_action('guidepost_appointment_completed', 'rewards_check_session_milestone');
add_action('guidepost_customer_created', 'rewards_check_referral_attribution');
add_action('guidepost_booking_created', 'rewards_process_referral_bonus');

// Redemption Hooks (modify GuidePost behavior)
add_filter('guidepost_booking_price', 'rewards_apply_session_credit');
add_action('guidepost_customer_dashboard', 'rewards_render_dashboard_widget');

// Data Access (use GuidePost functions)
$customer = GuidePost_Database::get_customer($customer_id);
$credits = $customer->total_credits; // Already exists in schema
```

### Database (Uses Existing GuidePost Tables)

**Already Exists:**
- `guidepost_customers.total_credits` - Current balance
- `guidepost_credit_history` - Transaction log with reasons

**New Table (in sister plugin):**
```sql
CREATE TABLE {prefix}guidepost_referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    referrer_customer_id BIGINT UNSIGNED NOT NULL,
    referee_customer_id BIGINT UNSIGNED,
    referral_code VARCHAR(20) NOT NULL,
    status ENUM('pending','completed','expired') DEFAULT 'pending',
    credited_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY referral_code (referral_code),
    KEY referrer_customer_id (referrer_customer_id)
);
```

**New Options (wp_options):**
```php
guidepost_rewards_settings = [
    'enabled' => true,
    'earning' => [
        'referral_booking' => 100,
        'sessions_5' => 25,
        'google_review' => 50,
        'anniversary_6mo' => 50,
        'milestone_10' => 50,
    ],
    'redemption' => [
        'resource_bundle' => ['cost' => 100, 'enabled' => true, 'url' => '...'],
        'session_credit_50' => ['cost' => 250, 'enabled' => true],
        'free_discovery' => ['cost' => 500, 'enabled' => true, 'service_id' => 1],
        'retreat_invite' => ['cost' => 1000, 'enabled' => true],
    ],
    'referral_code_prefix' => 'SOJOURN',
];
```

---

## Questions for Kim

Before implementation, please confirm:

1. **Earning Amounts:**
   - Are the credit amounts appropriate? (100 for referral, 25 for 5 sessions, etc.)
   - Any actions to add or remove?

2. **Redemption Values:**
   - Is $50 the right discount amount for 250 credits?
   - Which service should be the "Free Discovery Session"?
   - What discount % or $ for retreat invitation?

3. **Resource Bundle:**
   - What content will be included? (workbooks, meditations, templates)
   - Do you have these ready or need to create?

4. **Referral Program:**
   - Should the referred person ALSO get 100 credits? (currently yes)
   - Any limit on how many referrals one person can make?

5. **Branding:**
   - "Sojourn Rewards" as the program name - approved?
   - Any changes to the landing page design?

6. **Launch Timing:**
   - When would you want to announce this to existing clients?
   - Should existing clients start with 0 credits or get a welcome bonus?

---

## Implementation Phases

### Phase 1: Foundation (Sister Plugin Setup)
- Create plugin structure
- Settings page with configuration
- Hook into GuidePost events
- Credit display in customer dashboard

### Phase 2: Earning System
- Referral code generation
- Referral tracking and attribution
- Session milestone detection
- Manual credit awards (reviews, anniversaries)

### Phase 3: Redemption System
- Resource bundle unlock
- $50 session credit (WooCommerce integration)
- Free Discovery session booking
- Retreat invitation workflow

### Phase 4: Polish
- Email notifications
- Landing page as WordPress shortcode
- Admin dashboard analytics
- Referral leaderboard

---

## Approval

- [ ] Kim approves earning structure
- [ ] Kim approves redemption rewards
- [ ] Kim confirms resource bundle content
- [ ] Kim approves "Sojourn Rewards" branding
- [ ] Ready to begin Phase 1

---

*This specification will be updated based on client feedback before implementation begins.*
