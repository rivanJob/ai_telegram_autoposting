<?php

declare(strict_types=1);

function renderLayout(string $title, string $content): void
{
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<style>
    :root{--bg:#0f172a;--panel:#111827;--card:#1f2937;--text:#e5e7eb;--muted:#94a3b8;--accent:#38bdf8}
    body{margin:0;font-family:Inter,Arial;background:var(--bg);color:var(--text)}
    .app{display:flex;min-height:100vh}.side{width:240px;background:#020617;padding:20px}
    .side a{display:block;color:var(--muted);padding:8px;border-radius:8px;text-decoration:none}.side a:hover{background:#1e293b;color:#fff}
    .main{flex:1;padding:24px}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.card{background:var(--card);padding:16px;border-radius:12px}
    input,select,textarea,button{width:100%;padding:10px;border-radius:8px;border:1px solid #334155;background:#0b1220;color:var(--text)}
    button{background:var(--accent);color:#00111f;font-weight:600;cursor:pointer}.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #334155;padding:8px;text-align:left}
    .pill{padding:3px 8px;border-radius:999px;background:#334155}
    </style></head><body><div class="app"><aside class="side"><h3>Автопостер</h3>
    <a href="/admin/index.php">Панель</a><a href="/admin/channels.php">Каналы</a><a href="/admin/themes.php">Темы</a>
    <a href="/admin/prompts.php">Менеджер промптов</a><a href="/admin/schedule.php">Конструктор расписания</a><a href="/admin/jobs.php">Монитор задач</a>
    <a href="/admin/test_lab.php">Тестовая лаборатория</a><a href="/admin/audit.php">Журнал аудита</a><a href="/admin/logout.php">Выход</a>
    </aside><main class="main">' . $content . '</main></div></body></html>';
}
