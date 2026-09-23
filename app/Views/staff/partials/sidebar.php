<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$base = '/DentalClinic/public';

$navItemsPrimary = [
    [
        'path' => '/staff/dashboard',
        'label' => 'Dashboard',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 13.2c0-.7.3-1.4.9-1.8l6.4-5.1a2.8 2.8 0 0 1 3.4 0l6.4 5.1c.6.5.9 1.1.9 1.8v6.2c0 1-.8 1.8-1.8 1.8h-4.4v-5.7H8.2v5.7H3.8c-1 0-1.8-.8-1.8-1.8v-6.2Z"/>
            </svg>
        ',
    ],
    [
        'path' => '/staff/patients',
        'label' => 'Patients',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 12.2a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm0 2.1c-4.2 0-7.6 2.4-8.4 5.8-.1.6.4 1.1 1 1.1h15c.6 0 1.1-.5 1-1.1-.8-3.4-4.2-5.8-8.6-5.8Z"/>
            </svg>
        ',
    ],
    [
        'path' => '/staff/patient-verification',
        'label' => 'Patient Verification',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 3.5 5.5 6.4v5.1c0 4.4 2.7 7.7 6.5 9 3.8-1.3 6.5-4.6 6.5-9V6.4L12 3.5Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
                <path d="m9 12 2 2 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        ',
    ],
  [
    'path' => '/staff/appointments',
    'label' => 'Appointments',
    'children' => [
        [
            'path' => '/staff/appointments',
            'label' => 'Today\'s Appointments',
        ],
        [
            'path' => '/staff/appointments/create',
            'label' => 'Walk-in',
        ],
        [
            'path' => '/staff/appointments/queue',
            'label' => 'Queue',
        ],
    ],
    'icon' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M7 2.8v2.1M17 2.8v2.1M4.8 7.1h14.4M6.1 4.9h11.8a1.7 1.7 0 0 1 1.7 1.7v12a1.7 1.7 0 0 1-1.7 1.7H6.1a1.7 1.7 0 0 1-1.7-1.7v-12A1.7 1.7 0 0 1 6.1 4.9Zm2.6 5.2h2.5v2.5H8.7v-2.5Zm4.1 0h2.5v2.5h-2.5v-2.5Z"
                  fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    ',
],
    [
        'path' => '/staff/appointment-requests',
        'label' => 'Appointment Requests',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4.5h7l4 4v10.7c0 .7-.6 1.3-1.3 1.3H7c-.7 0-1.3-.6-1.3-1.3V5.8c0-.7.6-1.3 1.3-1.3Zm7 0v4h4M8.6 12h6.8M8.6 15.5h6.8M8.6 8.6h2.8"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        ',
    ],
];

$navItemsSecondary = [
    [
        'path' => '/staff/messages',
        'label' => 'Messages',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5.5 6.2h13a1.8 1.8 0 0 1 1.8 1.8v7a1.8 1.8 0 0 1-1.8 1.8h-8.4l-3.8 2.8v-2.8H5.5A1.8 1.8 0 0 1 3.7 15V8a1.8 1.8 0 0 1 1.8-1.8Z"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        ',
    ],
   [
    'path' => '/staff/billing',
    'label' => 'Billing',
    'icon' => '
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6 2h12a1 1 0 0 1 1 1v18l-3-1.5L13 21l-3-1.5L7 21l-3-1.5V3a1 1 0 0 1 1-1Zm2 5v2h8V7H8Zm0 4v2h8v-2H8Zm0 4v2h5v-2H8Z"/>
        </svg>
    ',
],
];

$navItemsSystem = [
    [
        'path' => '/DentalClinic/public/',
        'label' => 'Home Page',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3.8 12.8 12 5.7l8.2 7.1M6.2 10.8v8.5h11.6v-8.5"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        ',
    ]
];
?>

<style>
    html,
    body {
        margin: 0;
        overflow-x: hidden;
    }

    .staff-sidebar {
        width: 248px;
        min-width: 248px;
        max-width: 248px;
        height: 100dvh;
        background: linear-gradient(180deg, #030509 0%, #040b15 100%);
        color: #dbe7f3;
        padding: 18px 6px 16px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: 16px;
        border-right: 1px solid rgba(255, 255, 255, 0.04);
        overflow: hidden;
        position: sticky;
        top: 0;
    }

    .staff-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 6px 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        flex-shrink: 0;
    }

    .staff-brand-logo {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: linear-gradient(135deg, #34d399 0%, #14b8a6 100%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-weight: 800;
        font-size: 14px;
        flex-shrink: 0;
        box-shadow: 0 8px 20px rgba(20, 184, 166, 0.28);
    }

    .staff-brand-copy {
        min-width: 0;
    }

    .staff-brand-title {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: #ffffff;
        line-height: 1.2;
    }

    .staff-brand-subtitle {
        margin-top: 2px;
        font-size: 11px;
        color: #8fa4bb;
        line-height: 1.3;
    }

    .staff-sidebar-menu {
        flex: 1;
        min-height: 0;
        overflow: hidden;
        display: grid;
        align-content: start;
        gap: 16px;
    }

    .staff-nav-group {
        display: grid;
        gap: 6px;
    }

    .staff-nav-label {
        padding: 0 10px;
        margin-bottom: 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #6f8297;
    }

    .staff-nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 11px 12px;
        border-radius: 12px;
        color: #c7d5e4;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: 0.2s ease;
        box-sizing: border-box;
        width: 100%;
        overflow: hidden;
        flex-shrink: 0;
    }

    .staff-nav-link:hover {
        background: rgba(255, 255, 255, 0.06);
        color: #ffffff;
    }

    .staff-nav-link.active {
        background: linear-gradient(90deg, rgba(99, 182, 172, 0.22) 0%, rgba(96, 172, 163, 0.12) 100%);
        color: #ffffff;
        
    }

    .staff-nav-icon {
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: inherit;
        flex-shrink: 0;
    }

    .staff-nav-icon svg {
        width: 18px;
        height: 18px;
        display: block;
        fill: currentColor;
    }

    .staff-nav-text {
        min-width: 0;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .staff-sidebar-footer {
        margin-top: auto;
        padding: 12px 10px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 11px;
        color: #7f93a8;
        line-height: 1.5;
        flex-shrink: 0;
    }

    @media (max-width: 900px) {
        .staff-sidebar {
            width: 100%;
            min-width: 100%;
            max-width: 100%;
            height: auto;
            min-height: auto;
            position: relative;
            top: auto;
            border-right: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            overflow: visible;
        }

        .staff-sidebar-menu {
            overflow: visible;
        }
    }

    @media (max-width: 520px) {
        .staff-sidebar {
            width: min(82vw, 290px);
            min-width: min(82vw, 290px);
            max-width: min(82vw, 290px);
            padding-left: 10px;
            padding-right: 10px;
        }

        .staff-brand-title {
            font-size: 14px;
        }

        .staff-nav-link {
            padding: 10px 10px;
        }
    }


.staff-nav-dropdown {
    display: grid;
    gap: 4px;
}

.staff-nav-dropdown-toggle {
    width: 100%;
    border: none;
    background: transparent;
    text-align: left;
}

.staff-nav-caret {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.25s ease;
}

.staff-nav-caret svg {
    width: 14px;
    height: 14px;
}

.staff-nav-dropdown.open .staff-nav-caret {
    transform: rotate(180deg);
}

.staff-nav-submenu {
    display: none;
    padding-left: 30px;
    gap: 4px;
}

.staff-nav-dropdown.open .staff-nav-submenu {
    display: grid;
}

.staff-nav-sublink {
    padding: 8px 10px;
    border-radius: 10px;
    color: #9fb2c7;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}

.staff-nav-sublink:hover,
.staff-nav-sublink.active {
    background: rgba(20, 184, 166, 0.16);
    color: #ffffff;
}
</style>

<aside class="staff-sidebar">
    <div class="staff-brand">
        <div class="staff-brand-logo"></div>
        <div class="staff-brand-copy">
            <h1 class="staff-brand-title">DentaLink</h1>
            <div class="staff-brand-subtitle">Staff workspace</div>
        </div>
    </div>

    <div class="staff-sidebar-menu">
        <div class="staff-nav-group">
            <div class="staff-nav-label">Menu</div>
            <?php foreach ($navItemsPrimary as $item): ?>
    <?php
        $hasChildren = !empty($item['children']);
        $isActive = $currentPath === $base . $item['path']
    || str_starts_with($currentPath, $base . $item['path'] . '/');
        $fullPath = $base . $item['path'];
    ?>

    <?php if ($hasChildren): ?>
        <div class="staff-nav-dropdown <?= $isActive ? 'open' : '' ?>">
           <button type="button" class="staff-nav-link staff-nav-dropdown-toggle <?= $isActive ? 'active' : '' ?>">
                <span class="staff-nav-icon"><?= $item['icon'] ?></span>
                <span class="staff-nav-text"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="staff-nav-caret">
    <svg viewBox="0 0 24 24">
        <path d="M6 9l6 6 6-6" 
              fill="none" 
              stroke="currentColor" 
              stroke-width="2" 
              stroke-linecap="round" 
              stroke-linejoin="round"/>
    </svg>
</span>
            </button>

            <div class="staff-nav-submenu">
                <?php foreach ($item['children'] as $child): ?>
                    <?php
                        $childFullPath = $base . $child['path'];
                        $childActive = $currentPath === $childFullPath;
                    ?>
                    <a href="<?= htmlspecialchars($childFullPath, ENT_QUOTES, 'UTF-8') ?>"
                       class="staff-nav-sublink <?= $childActive ? 'active' : '' ?>">
                        <?= htmlspecialchars($child['label'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <a href="<?= htmlspecialchars($fullPath, ENT_QUOTES, 'UTF-8') ?>"
           class="staff-nav-link<?= $isActive ? ' active' : '' ?>">
            <span class="staff-nav-icon"><?= $item['icon'] ?></span>
            <span class="staff-nav-text"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    <?php endif; ?>
<?php endforeach; ?>
        </div>

        <div class="staff-nav-group">
            <div class="staff-nav-label">Clinic Tools</div>
            <?php foreach ($navItemsSecondary as $item): ?>
                <?php
                    $fullPath = $base . $item['path'];
                    $isActive = str_contains($currentPath, $item['path']);
                ?>
                <a href="<?= htmlspecialchars($fullPath, ENT_QUOTES, 'UTF-8') ?>"
                   class="staff-nav-link<?= $isActive ? ' active' : '' ?>">
                    <span class="staff-nav-icon"><?= $item['icon'] ?></span>
                    <span class="staff-nav-text"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="staff-nav-group">
            <div class="staff-nav-label">System</div>
            <?php foreach ($navItemsSystem as $item): ?>
                <?php
                    $fullPath = str_starts_with($item['path'], '/DentalClinic/public/')
                        ? $item['path']
                        : $base . $item['path'];
                    $isActive = $item['path'] !== '/DentalClinic/public/' && str_contains($currentPath, str_replace($base, '', $item['path']));
                ?>
                <a href="<?= htmlspecialchars($fullPath, ENT_QUOTES, 'UTF-8') ?>"
                   class="staff-nav-link<?= $isActive ? ' active' : '' ?>">
                    <span class="staff-nav-icon"><?= $item['icon'] ?></span>
                    <span class="staff-nav-text"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="staff-sidebar-footer">
        Staff Panel<br>
        Dental Clinic Management System
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.staff-nav-dropdown-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const dropdown = button.closest('.staff-nav-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('open');
            }
        });
    });
});
</script>