<?php
// Ensure this file is not accessed directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    die('Access Denied');
}

/**
 * Determines the budget access privileges for a given user.
 *
 * @param string|null $username The username of the logged-in user.
 * @param string|null $role The role of the logged-in user ('admin' or 'officer').
 * @return array An associative array with access control settings.
 * - 'source': array, The default budget source(s).
 * - 'locked': bool, Whether the budget source dropdown is locked.
 * - 'owners_table': array, The database table(s) for budget owners.
 * - 'owners_filter': array, A list of specific budget owner names to filter by.
 */
function getUserBudgetAccess(?string $username, ?string $role): array
{
    // Default to a "deny all" policy
    $access = [
        'source' => [],
        'locked' => true,
        'owners_table' => [],
        'owners_filter' => []
    ];

    if ($role === 'admin') {
        // Admins have full access and no restrictions
        $access['source'] = ['Government Budget', 'Programs Budget'];
        $access['locked'] = false;
        $access['owners_table'] = ['budget_owners', 'p_budget_owners'];
        $access['owners_filter'] = []; // No filter means all owners are visible
        return $access;
    }

    if ($role === 'officer') {
        switch ($username) {
            // Lemlem Gebru is the Governmental Budget Finance Officer
            case 'lemlem': // Assuming 'lemlem' is the username for Lemlem Gebru
                $access['source'] = ['Government Budget'];
                $access['locked'] = true;
                $access['owners_table'] = ['budget_owners'];
                $access['owners_filter'] = []; // No filter, can manage all governmental budgets
                break;

            // Officer2 can manage all governmental budgets
            case 'officer2':
                $access['source'] = ['Government Budget'];
                $access['locked'] = true;
                $access['owners_table'] = ['budget_owners'];
                $access['owners_filter'] = [];
                break;
            
            // Program officers with specific budget assignments
            case 'idris':
                $access['source'] = ['Programs Budget'];
                $access['locked'] = true;
                $access['owners_table'] = ['p_budget_owners'];
                $access['owners_filter'] = ['CPC Coop and Human Capital'];
                break;

            case 'sudeys':
                $access['source'] = ['Programs Budget'];
                $access['locked'] = true;
                $access['owners_table'] = ['p_budget_owners'];
                $access['owners_filter'] = ['Wash'];
                break;

            case 'mekla':
                $access['source'] = ['Programs Budget'];
                $access['locked'] = true;
                $access['owners_table'] = ['p_budget_owners'];
                $access['owners_filter'] = ['Teradio', 'Africa CDC'];
                break;

            case 'alex':
                $access['source'] = ['Programs Budget'];
                $access['locked'] = true;
                $access['owners_table'] = ['p_budget_owners'];
                $access['owners_filter'] = ['GAVI World Bank', 'Afar Essential'];
                break;

            case 'zeru':
                $access['source'] = ['Programs Budget'];
                $access['locked'] = true;
                $access['owners_table'] = ['p_budget_owners'];
                $access['owners_filter'] = ['UNDAF', 'SDG'];
                break;

            // Any other officer has no access by default
            default:
                // The default "deny all" policy applies
                break;
        }
    }

    return $access;
}
