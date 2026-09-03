<?php
// crm/excel-import.php
$pageTitle = "Excel Sheet Import";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/db.php';

require_permission('inquiries', 'create');

// ---- AJAX: Process imported rows (JSON POST from JS) ----
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');
    $raw   = file_get_contents('php://input');
    $rows  = json_decode($raw, true);

    if (!is_array($rows)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    $results = ['imported' => 0, 'duplicates' => 0, 'errors' => 0, 'log' => []];

    foreach ($rows as $idx => $row) {
        $rowNum = $idx + 2; // Row 2 onwards (Row 1 = headers)

        // Normalize keys: lowercase, strip ? and trim spaces
        $r = [];
        foreach ($row as $k => $v) {
            $key = strtolower(trim(str_replace(['?', ' '], ['', '_'], $k)));
            $r[$key] = is_string($v) ? trim($v) : $v;
        }

        $externalId  = $r['id'] ?? '';
        $fullName    = $r['full_name'] ?? '';
        $phoneNumber = $r['phone_number'] ?? '';
        $email       = $r['email'] ?? '';

        // Skip completely blank rows
        if (empty($externalId) && empty($fullName) && empty($phoneNumber)) {
            continue;
        }

        // Basic validation
        if (empty($fullName) || empty($phoneNumber)) {
            $results['errors']++;
            $results['log'][] = ['row' => $rowNum, 'status' => 'error', 'msg' => "Row $rowNum: full_name/phone_number missing"];
            continue;
        }

        // Duplicate: by external ID
        if (!empty($externalId)) {
            $stmtD = $pdo->prepare("SELECT id FROM inquiries WHERE meta_lead_id = ?");
            $stmtD->execute([$externalId]);
            if ($stmtD->fetch()) {
                $results['duplicates']++;
                $results['log'][] = ['row' => $rowNum, 'status' => 'duplicate', 'msg' => "Row $rowNum ($fullName): duplicate meta_lead_id"];
                continue;
            }
        }

        // Duplicate: by phone (last 10 digits)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        $phoneLast10 = strlen($cleanPhone) >= 10 ? '%' . substr($cleanPhone, -10) : $cleanPhone;
        $stmtP = $pdo->prepare("
            SELECT id FROM inquiries
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')','') LIKE ?
        ");
        $stmtP->execute([$phoneLast10]);
        if ($stmtP->fetch()) {
            $results['duplicates']++;
            $results['log'][] = ['row' => $rowNum, 'status' => 'duplicate', 'msg' => "Row $rowNum ($fullName): duplicate phone"];
            continue;
        }

        // Parse created_time
        $createdAt = date('Y-m-d H:i:s');
        $ct = $r['created_time'] ?? '';
        if ($ct) {
            $ts = strtotime($ct);
            if ($ts !== false) $createdAt = date('Y-m-d H:i:s', $ts);
        }

        $campaignName = $r['campaign_name'] ?? null;
        $isOrganic    = !empty($r['is_organic']) && in_array(strtolower((string)$r['is_organic']), ['1','true','yes']) ? 1 : 0;

        // Build message summary
        $msg = "Excel Import Lead.\n";
        if (!empty($r['are_you_looking_for']))             $msg .= "Looking For: {$r['are_you_looking_for']}\n";
        if (!empty($r['budget']))                          $msg .= "Budget: {$r['budget']}\n";
        if (!empty($r['purchase_time']))                   $msg .= "Purchase Time: {$r['purchase_time']}\n";
        if (!empty($r['have_you_invested_in_property_before'])) $msg .= "Invested Before: {$r['have_you_invested_in_property_before']}\n";
        if (!empty($r['form_name']))                       $msg .= "Form: {$r['form_name']}\n";

        try {
            $sql = "INSERT INTO inquiries (
                        property_id, name, email, phone, message, status, source, campaign_name,
                        meta_lead_id, ad_id, ad_name, adset_id, adset_name, campaign_id,
                        form_id, form_name, is_organic, platform,
                        are_you_looking_for, budget, purchase_time,
                        have_you_invested_in_property_before, lead_status, created_at
                    ) VALUES (
                        NULL, ?, ?, ?, ?, 'fresh_lead', 'meta_ads', ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?
                    )";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $fullName,
                $email ?: null,
                $phoneNumber,
                $msg,
                $campaignName,

                $externalId ?: null,
                $r['ad_id'] ?? null,
                $r['ad_name'] ?? null,
                $r['adset_id'] ?? null,
                $r['adset_name'] ?? null,
                $r['campaign_id'] ?? null,

                $r['form_id'] ?? null,
                $r['form_name'] ?? null,
                $isOrganic,
                $r['platform'] ?? null,

                $r['are_you_looking_for'] ?? null,
                $r['budget'] ?? null,
                $r['purchase_time'] ?? null,
                $r['have_you_invested_in_property_before'] ?? null,
                $r['lead_status'] ?? 'received',
                $createdAt,
            ]);

            $results['imported']++;
            $results['log'][] = ['row' => $rowNum, 'status' => 'success', 'msg' => "Row $rowNum ($fullName): imported ✓"];
        } catch (\PDOException $e) {
            $results['errors']++;
            $results['log'][] = ['row' => $rowNum, 'status' => 'error', 'msg' => "Row $rowNum: DB error – " . $e->getMessage()];
        }
    }

    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}
?>

<!-- SheetJS for Excel parsing (CDN, no server dependency) -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>

<!-- Page Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <h2 class="text-xl font-black text-slate-800 leading-none">Excel Sheet Import</h2>
        <p class="text-slate-400 text-xs mt-1">Google Sheet থেকে export করেলી CSV/Excel file upload করুন और leads automatically import करें।</p>
    </div>
    <a href="google-sheet-leads.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
        View Imported Leads
    </a>
</div>

<!-- Steps Guide Banner -->
<div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl p-5 mb-8 shadow-sm">
    <div class="flex items-start gap-4 flex-wrap">
        <div class="flex-1 min-w-[200px]">
            <h3 class="font-extrabold text-sm mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                How to Export from Google Sheet
            </h3>
            <div class="flex flex-wrap gap-5 text-xs text-blue-100">
                <div class="flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center font-extrabold text-white text-[10px] flex-shrink-0">1</span>
                    Open "Wave City Meta Leads" Google Sheet
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center font-extrabold text-white text-[10px] flex-shrink-0">2</span>
                    File → Download → <strong class="text-white">CSV (.csv)</strong> or <strong class="text-white">Excel (.xlsx)</strong>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center font-extrabold text-white text-[10px] flex-shrink-0">3</span>
                    Upload that file below — duplicates are auto-skipped!
                </div>
            </div>
        </div>
        <div class="text-right">
            <span class="text-[9px] bg-white/15 border border-white/20 rounded-lg px-2 py-1 font-bold uppercase tracking-wider">Supports .csv & .xlsx</span>
        </div>
    </div>
</div>

<div class="row g-5">
    <!-- Upload Section -->
    <div class="col-lg-5">
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 h-full">
            <h3 class="text-base font-extrabold text-slate-800 mb-1">Upload File</h3>
            <p class="text-slate-400 text-xs font-light mb-6">Row 1 must be the header row with column names.</p>

            <!-- Drag-Drop Zone -->
            <div id="dropZone"
                class="border-2 border-dashed border-slate-300 rounded-2xl p-10 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition-all duration-200 group"
                onclick="document.getElementById('fileInput').click()"
                ondragover="event.preventDefault(); this.classList.add('border-blue-500','bg-blue-50')"
                ondragleave="this.classList.remove('border-blue-500','bg-blue-50')"
                ondrop="handleDrop(event)">
                <div class="w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-105 transition-transform">
                    <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <p class="font-bold text-slate-700 text-sm mb-1">Drop your Excel or CSV file here</p>
                <p class="text-slate-400 text-xs">or click to browse your computer</p>
                <p class="text-[10px] text-slate-300 mt-2 font-medium">.xlsx, .xls, .csv files supported</p>
            </div>

            <input type="file" id="fileInput" accept=".csv,.xlsx,.xls" class="hidden" onchange="handleFile(this.files[0])">

            <!-- File Info -->
            <div id="fileInfo" class="hidden mt-4 p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs flex items-center gap-3">
                <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div id="fileName" class="font-bold text-slate-800 truncate"></div>
                    <div id="fileStats" class="text-slate-400"></div>
                </div>
                <button onclick="clearFile()" class="text-slate-400 hover:text-rose-500 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Column Mapping Info -->
            <div id="mappingInfo" class="hidden mt-4">
                <div class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest mb-2">Detected Headers</div>
                <div id="headerTags" class="flex flex-wrap gap-1.5"></div>
            </div>

            <!-- Import Button -->
            <button id="importBtn" onclick="startImport()" disabled
                class="w-full mt-5 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white rounded-xl text-sm font-extrabold transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Import Leads Now
            </button>
        </div>
    </div>

    <!-- Preview & Results Section -->
    <div class="col-lg-7">

        <!-- Progress Bar (hidden until import) -->
        <div id="progressSection" class="hidden bg-white rounded-2xl border border-slate-200/60 shadow-sm p-6 mb-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-extrabold text-slate-800">Importing...</span>
                <span id="progressText" class="text-xs font-bold text-blue-600">0%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                <div id="progressBar" class="h-3 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-300" style="width:0%"></div>
            </div>
        </div>

        <!-- Stats Cards (after import) -->
        <div id="statsSection" class="hidden grid grid-cols-3 gap-4 mb-5">
            <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 text-center">
                <div id="statImported" class="text-2xl font-black text-emerald-600">0</div>
                <div class="text-[10px] text-emerald-500 font-extrabold uppercase tracking-wider mt-0.5">Imported</div>
            </div>
            <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-center">
                <div id="statDuplicate" class="text-2xl font-black text-amber-600">0</div>
                <div class="text-[10px] text-amber-500 font-extrabold uppercase tracking-wider mt-0.5">Duplicates</div>
            </div>
            <div class="bg-rose-50 border border-rose-100 rounded-xl p-4 text-center">
                <div id="statErrors" class="text-2xl font-black text-rose-600">0</div>
                <div class="text-[10px] text-rose-500 font-extrabold uppercase tracking-wider mt-0.5">Errors</div>
            </div>
        </div>

        <!-- Data Preview Table -->
        <div id="previewSection" class="hidden bg-white rounded-2xl border border-slate-200/60 shadow-sm">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-800">Data Preview</h3>
                    <p id="previewSubtitle" class="text-[10px] text-slate-400 mt-0.5"></p>
                </div>
                <span id="totalBadge" class="px-2.5 py-1 bg-blue-50 text-blue-600 text-[10px] font-extrabold rounded-full border border-blue-100"></span>
            </div>
            <div class="overflow-auto max-h-[460px]">
                <table class="w-full text-xs" id="previewTable">
                    <thead class="sticky top-0 bg-slate-50 text-[10px] text-slate-400 font-extrabold uppercase tracking-wider">
                        <tr id="previewHeaders"></tr>
                    </thead>
                    <tbody id="previewBody" class="divide-y divide-slate-50"></tbody>
                </table>
            </div>
        </div>

        <!-- Import Log -->
        <div id="logSection" class="hidden bg-white rounded-2xl border border-slate-200/60 shadow-sm mt-5">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="text-sm font-extrabold text-slate-800">Import Log</h3>
                <a href="google-sheet-leads.php" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition">
                    View All Leads →
                </a>
            </div>
            <div id="logBody" class="p-4 space-y-2 max-h-64 overflow-y-auto font-mono text-[10px]"></div>
        </div>

        <!-- Empty state -->
        <div id="emptyState" class="bg-white rounded-2xl border border-slate-200/60 shadow-sm p-16 text-center">
            <div class="w-20 h-20 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
            </div>
            <p class="text-slate-400 text-sm font-semibold mb-1">No file selected yet</p>
            <p class="text-slate-300 text-xs">Upload a CSV or Excel file to preview and import leads</p>
        </div>
    </div>
</div>

<script>
let parsedRows = [];

// Required columns from Google Sheet
const REQUIRED_COLS = ['full_name', 'phone_number'];
const IMPORTANT_COLS = ['id','created_time','ad_name','campaign_name','form_name',
                        'platform','are_you_looking_for','budget','purchase_time',
                        'have_you_invested_in_property_before','email','lead_status'];

function normalizeKey(k) {
    return k.toString().trim().toLowerCase()
        .replace(/[\?\s]+/g, '_')
        .replace(/_+$/, '');
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').classList.remove('border-blue-500','bg-blue-50');
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
}

function handleFile(file) {
    if (!file) return;
    const allowedExts = ['csv','xlsx','xls'];
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExts.includes(ext)) {
        alert('Please upload a .csv, .xlsx, or .xls file.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        let rows = [];
        if (ext === 'csv') {
            rows = parseCSV(e.target.result);
        } else {
            const data = new Uint8Array(e.target.result);
            const wb   = XLSX.read(data, {type: 'array', cellDates: true});
            const ws   = wb.Sheets[wb.SheetNames[0]];
            rows = XLSX.utils.sheet_to_json(ws, {defval: '', raw: false});
        }

        // Normalize keys
        parsedRows = rows.map(r => {
            const norm = {};
            for (const k in r) norm[normalizeKey(k)] = r[k];
            return norm;
        });

        // Filter blank rows
        parsedRows = parsedRows.filter(r =>
            Object.values(r).some(v => String(v).trim() !== '')
        );

        renderFileInfo(file, parsedRows.length);
        renderHeaders(parsedRows);
        renderPreview(parsedRows);
        document.getElementById('importBtn').disabled = parsedRows.length === 0;
    };

    if (ext === 'csv') reader.readAsText(file, 'UTF-8');
    else reader.readAsArrayBuffer(file);
}

function parseCSV(text) {
    const lines = text.split(/\r?\n/).filter(l => l.trim());
    if (lines.length < 2) return [];
    const headers = splitCSVLine(lines[0]).map(h => h.replace(/^"|"$/g,''));
    return lines.slice(1).map(line => {
        const vals = splitCSVLine(line).map(v => v.replace(/^"|"$/g,''));
        const obj = {};
        headers.forEach((h, i) => obj[h] = vals[i] ?? '');
        return obj;
    });
}

function splitCSVLine(line) {
    const result = [];
    let cur = '', inQ = false;
    for (let i = 0; i < line.length; i++) {
        const c = line[i];
        if (c === '"') { inQ = !inQ; continue; }
        if (c === ',' && !inQ) { result.push(cur); cur = ''; continue; }
        cur += c;
    }
    result.push(cur);
    return result;
}

function renderFileInfo(file, count) {
    document.getElementById('dropZone').classList.add('hidden');
    document.getElementById('fileInfo').classList.remove('hidden');
    document.getElementById('mappingInfo').classList.remove('hidden');
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileStats').textContent = count + ' data rows detected';
}

function renderHeaders(rows) {
    const headers = rows.length > 0 ? Object.keys(rows[0]) : [];
    const container = document.getElementById('headerTags');
    container.innerHTML = '';
    headers.forEach(h => {
        const isImportant = REQUIRED_COLS.includes(h) || IMPORTANT_COLS.includes(h);
        const el = document.createElement('span');
        el.className = `px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider ${
            REQUIRED_COLS.includes(h)
                ? 'bg-blue-100 text-blue-600 border border-blue-200'
                : isImportant
                    ? 'bg-slate-100 text-slate-600 border border-slate-200'
                    : 'bg-slate-50 text-slate-400 border border-slate-100'
        }`;
        el.textContent = h;
        container.appendChild(el);
    });
}

function renderPreview(rows) {
    if (!rows.length) return;
    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('previewSection').classList.remove('hidden');
    document.getElementById('previewSubtitle').textContent = `Showing first ${Math.min(rows.length,10)} of ${rows.length} rows`;
    document.getElementById('totalBadge').textContent = rows.length + ' rows';

    const headers = Object.keys(rows[0]);
    const displayCols = ['full_name','phone_number','email','campaign_name','budget','are_you_looking_for','lead_status','id'];
    const show = displayCols.filter(c => headers.includes(c));

    const thead = document.getElementById('previewHeaders');
    thead.innerHTML = show.map(h =>
        `<th class="px-4 py-3 text-left whitespace-nowrap">${h}</th>`
    ).join('');

    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = rows.slice(0,10).map(r =>
        `<tr class="hover:bg-slate-50">${show.map(h =>
            `<td class="px-4 py-2.5 text-slate-600 whitespace-nowrap max-w-[180px] truncate">${r[h] ?? ''}</td>`
        ).join('')}</tr>`
    ).join('');
}

function clearFile() {
    parsedRows = [];
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').classList.add('hidden');
    document.getElementById('mappingInfo').classList.add('hidden');
    document.getElementById('dropZone').classList.remove('hidden');
    document.getElementById('previewSection').classList.add('hidden');
    document.getElementById('statsSection').classList.add('hidden');
    document.getElementById('logSection').classList.add('hidden');
    document.getElementById('emptyState').classList.remove('hidden');
    document.getElementById('importBtn').disabled = true;
}

async function startImport() {
    if (!parsedRows.length) return;

    const btn = document.getElementById('importBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg> Importing...';

    // Show progress
    document.getElementById('progressSection').classList.remove('hidden');
    document.getElementById('previewSection').classList.add('hidden');
    document.getElementById('statsSection').classList.add('hidden');
    document.getElementById('logSection').classList.add('hidden');

    // Send in batches of 50
    const batchSize = 50;
    const total = parsedRows.length;
    let processed = 0;
    const allResults = { imported: 0, duplicates: 0, errors: 0, log: [] };

    for (let i = 0; i < total; i += batchSize) {
        const batch = parsedRows.slice(i, i + batchSize);
        try {
            const resp = await fetch('excel-import.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(batch)
            });
            const data = await resp.json();
            if (data.success) {
                allResults.imported   += data.results.imported;
                allResults.duplicates += data.results.duplicates;
                allResults.errors     += data.results.errors;
                allResults.log        = allResults.log.concat(data.results.log);
            }
        } catch (e) {
            console.error('Batch error:', e);
        }

        processed = Math.min(i + batchSize, total);
        const pct = Math.round((processed / total) * 100);
        document.getElementById('progressBar').style.width = pct + '%';
        document.getElementById('progressText').textContent = pct + '%';
    }

    // Hide progress, show results
    document.getElementById('progressSection').classList.add('hidden');
    showResults(allResults);

    btn.disabled = false;
    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg> Import Again';
}

function showResults(r) {
    document.getElementById('statsSection').classList.remove('hidden');
    document.getElementById('statImported').textContent  = r.imported;
    document.getElementById('statDuplicate').textContent = r.duplicates;
    document.getElementById('statErrors').textContent    = r.errors;

    document.getElementById('logSection').classList.remove('hidden');
    const logBody = document.getElementById('logBody');
    logBody.innerHTML = r.log.map(l => {
        let cls = l.status === 'success'
            ? 'bg-emerald-50 text-emerald-700 border-emerald-100'
            : l.status === 'duplicate'
                ? 'bg-amber-50 text-amber-700 border-amber-100'
                : 'bg-rose-50 text-rose-700 border-rose-100';
        return `<div class="p-2 rounded border ${cls}">${l.msg}</div>`;
    }).join('') || '<div class="text-slate-400 text-center py-4">No log entries.</div>';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
