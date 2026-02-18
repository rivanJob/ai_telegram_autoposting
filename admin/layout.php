<?php

declare(strict_types=1);

function renderLayout(string $title, string $content): void
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $menu = [
        '/admin/index.php' => 'Панель',
        '/admin/channels.php' => 'Каналы',
        '/admin/themes.php' => 'Темы',
        '/admin/prompts.php' => 'Менеджер промптов',
        '/admin/schedule.php' => 'Конструктор расписания',
        '/admin/jobs.php' => 'Монитор задач',
        '/admin/test_lab.php' => 'Тестовая лаборатория',
        '/admin/audit.php' => 'Журнал аудита',
        '/admin/logout.php' => 'Выход',
    ];

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<style>
    :root{--bg:#020617;--bg-soft:#0f172a;--panel:#111827;--card:#1f2937;--line:#334155;--text:#e2e8f0;--muted:#94a3b8;--accent:#38bdf8;--ok:#22c55e;--warn:#f59e0b;--danger:#f87171}
    *{box-sizing:border-box} body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:radial-gradient(circle at top,#0f172a,#020617 55%);color:var(--text)}
    .app{display:flex;min-height:100vh}.side{width:270px;background:rgba(2,6,23,.85);backdrop-filter:blur(6px);border-right:1px solid #1e293b;padding:24px 16px;position:sticky;top:0;height:100vh;overflow:auto}
    .brand{font-weight:700;margin-bottom:6px}.sub{color:var(--muted);font-size:12px;margin-bottom:16px}.side a{display:block;color:var(--muted);padding:10px 12px;border-radius:10px;text-decoration:none;margin-bottom:4px}
    .side a:hover{background:#1e293b;color:#fff}.side a.active{background:linear-gradient(90deg,#0ea5e9,#38bdf8);color:#03111d;font-weight:600}
    .main{flex:1;padding:26px;max-width:calc(100vw - 270px)} .top{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}
    .title{margin:0;font-size:28px}.hint{color:var(--muted);font-size:13px}
    .panel{background:rgba(15,23,42,.8);border:1px solid #1e293b;border-radius:14px;padding:16px;margin-bottom:16px;box-shadow:0 8px 30px rgba(2,6,23,.35)}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.card{background:linear-gradient(180deg,#1e293b,#111827);border:1px solid #334155;padding:14px;border-radius:12px}
    .metric{font-size:28px;font-weight:700;margin:8px 0 0}.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media (max-width:980px){.app{display:block}.side{position:relative;width:auto;height:auto}.main{max-width:100%}.row{grid-template-columns:1fr}}
    input,select,textarea,button{width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#0b1220;color:var(--text)}
    textarea{resize:vertical} button{background:linear-gradient(90deg,#0ea5e9,#38bdf8);color:#03111d;font-weight:700;cursor:pointer;border:0}
    button.secondary{background:#1e293b;color:var(--text);border:1px solid var(--line)} .chip{display:inline-block;padding:4px 9px;border-radius:999px;background:#1e293b;color:#cbd5e1;font-size:12px}
    table{width:100%;border-collapse:collapse;font-size:14px}td,th{border-bottom:1px solid #243348;padding:10px;text-align:left;vertical-align:top}
    tr:hover td{background:rgba(51,65,85,.2)} .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
    .status{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600}
    .status.NEW{background:rgba(56,189,248,.2);color:#7dd3fc}.status.RUNNING{background:rgba(245,158,11,.2);color:#fbbf24}.status.DONE{background:rgba(34,197,94,.2);color:#86efac}.status.ERROR{background:rgba(248,113,113,.2);color:#fca5a5}
    .error{color:var(--danger)} .stack{display:grid;gap:12px}
    </style></head><body><div class="app"><aside class="side"><div class="brand">Автопостер</div><div class="sub">Production control panel</div>';
    foreach ($menu as $href => $label) {
        $isActive = str_starts_with($path, $href) ? ' active' : '';
        echo '<a class="' . $isActive . '" href="' . $href . '">' . htmlspecialchars($label) . '</a>';
    }
    echo '</aside><main class="main"><div class="top"><h1 class="title">' . htmlspecialchars($title) . '</h1><div class="hint">' . date('Y-m-d H:i:s') . '</div></div>' . $content . '</main></div></body></html>';
}
