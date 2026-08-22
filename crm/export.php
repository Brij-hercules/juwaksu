<?php
// crm/export.php
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

// Route Protection
if (!is_logged_in()) {
    die("Unauthorized access.");
}

$module = isset($_GET['module']) ? trim($_GET['module']) : '';
$format = isset($_GET['format']) ? trim($_GET['format']) : 'csv'; // 'csv' or 'pdf'

if (empty($module)) {
    die("Invalid request. Module parameter is required.");
}

// Check permission for this module view
if ($module === 'meta_leads') {
    require_permission('meta_ads', 'view');
} else {
    require_permission($module, 'view');
}

// ----------------------------------------------------
// 1. Gather data according to module & active filters
// ----------------------------------------------------
$data = [];
$filename = $module . "_export_" . date("Ymd_His");

switch ($module) {
    case 'properties':
        $filterCategory = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        $filterStatus   = isset($_GET['status']) ? trim($_GET['status']) : '';
        $filterKisan    = isset($_GET['is_kisan_kota']) ? trim($_GET['is_kisan_kota']) : '';
        
        $sql = "SELECT p.*, c.name as category_name FROM properties p JOIN categories c ON p.category_id = c.id WHERE 1=1";
        $params = [];
        
        if ($filterCategory > 0) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filterCategory;
        }
        if (!empty($filterStatus)) {
            $sql .= " AND p.status = ?";
            $params[] = $filterStatus;
        }
        if ($filterKisan !== '') {
            $sql .= " AND p.is_kisan_kota = ?";
            $params[] = intval($filterKisan);
        }
        $sql .= " ORDER BY p.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        $headers = ['ID', 'Title', 'Category', 'Location', 'Price', 'Price Unit', 'Area (SqFt)', 'Featured', 'Kisan Kota', 'Status', 'Date Created'];
        break;

    case 'categories':
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY id DESC");
        $data = $stmt->fetchAll();
        $headers = ['ID', 'Name', 'Slug', 'Description', 'Date Created'];
        break;

    case 'inquiries':
        $filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
        $filterSource = isset($_GET['source']) ? trim($_GET['source']) : '';
        
        $sql = "
            SELECT i.*, p.title as property_title, u.username as agent_name 
            FROM inquiries i 
            LEFT JOIN properties p ON i.property_id = p.id 
            LEFT JOIN users u ON i.assigned_to = u.id 
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filterStatus)) {
            $sql .= " AND i.status = ?";
            $params[] = $filterStatus;
        }
        if (!empty($filterSource)) {
            $sql .= " AND i.source = ?";
            $params[] = $filterSource;
        }
        $sql .= " ORDER BY i.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        $headers = ['ID', 'Client Name', 'Email', 'Phone', 'Message', 'Status', 'Assigned Agent', 'Source', 'Campaign Name', 'Date Logged'];
        break;

    case 'meta_leads':
        $filterCampaign = isset($_GET['campaign_name']) ? trim($_GET['campaign_name']) : '';
        $filterStatus   = isset($_GET['status']) ? trim($_GET['status']) : '';
        $filterStart    = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
        $filterEnd      = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
        
        $sql = "SELECT * FROM inquiries WHERE source = 'meta_ads'";
        $params = [];
        
        if (!empty($filterCampaign)) {
            $sql .= " AND campaign_name = ?";
            $params[] = $filterCampaign;
        }
        if (!empty($filterStatus)) {
            $sql .= " AND status = ?";
            $params[] = $filterStatus;
        }
        if (!empty($filterStart)) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filterStart;
        }
        if (!empty($filterEnd)) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filterEnd;
        }
        $sql .= " ORDER BY id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        $headers = ['ID', 'Client Name', 'Email', 'Phone', 'Campaign Name', 'Status', 'Date Captured'];
        break;

    case 'users':
        $stmt = $pdo->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC");
        $data = $stmt->fetchAll();
        $headers = ['ID', 'Username', 'Email', 'Role', 'Status', 'Date Created'];
        break;

    default:
        die("Unsupported export module.");
}

// ----------------------------------------------------
// 2. Format Output (CSV or PDF/Print layout)
// ----------------------------------------------------

if ($format === 'csv') {
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Output headers
    fputcsv($output, $headers);
    
    // Output rows matching structure
    foreach ($data as $row) {
        $csvRow = [];
        switch ($module) {
            case 'properties':
                $csvRow = [
                    $row['id'],
                    $row['title'],
                    $row['category_name'],
                    $row['location'],
                    $row['price'],
                    $row['price_unit'],
                    $row['area_sqft'],
                    $row['featured'] ? 'Yes' : 'No',
                    $row['is_kisan_kota'] ? 'Yes' : 'No',
                    $row['status'],
                    $row['created_at']
                ];
                break;
            case 'categories':
                $csvRow = [$row['id'], $row['name'], $row['slug'], $row['description'], $row['created_at']];
                break;
            case 'inquiries':
                $csvRow = [
                    $row['id'],
                    $row['name'],
                    $row['email'],
                    $row['phone'],
                    $row['message'],
                    $row['status'],
                    $row['agent_name'] ?? 'Unassigned',
                    $row['source'],
                    $row['campaign_name'] ?? 'N/A',
                    $row['created_at']
                ];
                break;
            case 'meta_leads':
                $csvRow = [
                    $row['id'],
                    $row['name'],
                    $row['email'],
                    $row['phone'],
                    $row['campaign_name'] ?? 'General Facebook Campaign',
                    $row['status'],
                    $row['created_at']
                ];
                break;
            case 'users':
                $csvRow = [$row['id'], $row['username'], $row['email'], $row['role_name'], $row['status'], $row['created_at']];
                break;
        }
        fputcsv($output, $csvRow);
    }
    
    fclose($output);
    exit;

} else {
    // PDF / HTML Printable Report View
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Report - <?php echo htmlspecialchars(ucfirst($module)); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @media print {
                body {
                    background-color: #ffffff;
                    color: #000000;
                    padding: 0;
                }
                .no-print {
                    display: none !important;
                }
                .print-card {
                    box-shadow: none !important;
                    border: none !important;
                }
            }
        </style>
    </head>
    <body class="bg-slate-100 py-10 px-4 font-sans antialiased text-slate-800">
        
        <!-- Controls bar -->
        <div class="max-w-5xl mx-auto mb-6 flex justify-between items-center no-print">
            <span class="text-xs text-slate-500">Press <strong>Ctrl + P</strong> to save this document as PDF or print.</span>
            <button onclick="window.print()" class="px-5 py-2.5 bg-brand-500 bg-slate-900 text-white font-bold text-xs rounded-xl shadow hover:bg-slate-800 transition">
                Print / Save as PDF
            </button>
        </div>
        
        <!-- Report Card Container -->
        <div class="max-w-5xl mx-auto bg-white p-8 md:p-12 rounded-3xl shadow-sm border border-slate-200/50 print-card">
            
            <!-- Header layout -->
            <div class="flex justify-between items-start border-b border-slate-200 pb-8 mb-8">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 uppercase">
                        <span class="text-slate-500">WAVE</span> PROPERTIES REPORT
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">Export Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $module))); ?> Directory Log</p>
                </div>
                <div class="text-right">
                    <div class="text-xs font-bold text-slate-700">Date Generated</div>
                    <div class="text-sm text-slate-500 font-medium"><?php echo date("d-M-Y H:i:s"); ?></div>
                </div>
            </div>
            
            <!-- Details Table Grid -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-300 text-slate-500 font-bold uppercase tracking-wider">
                            <?php foreach ($headers as $header): ?>
                                <th class="pb-3 px-2"><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="<?php echo count($headers); ?>" class="py-6 text-center text-slate-400">No records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php if ($module === 'properties'): ?>
                                        <td class="py-3.5 px-2 font-bold">#<?php echo $row['id']; ?></td>
                                        <td class="py-3.5 px-2 font-semibold text-slate-900"><?php echo htmlspecialchars($row['title']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['location']); ?></td>
                                        <td class="py-3.5 px-2 font-bold">₹<?php echo number_format($row['price']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['price_unit']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo number_format($row['area_sqft']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo $row['featured'] ? 'Yes' : 'No'; ?></td>
                                        <td class="py-3.5 px-2 font-semibold <?php echo $row['is_kisan_kota'] ? 'text-amber-600' : ''; ?>"><?php echo $row['is_kisan_kota'] ? 'Yes (8%)' : 'No'; ?></td>
                                        <td class="py-3.5 px-2 font-bold uppercase"><?php echo htmlspecialchars($row['status']); ?></td>
                                        <td class="py-3.5 px-2 text-slate-400"><?php echo date('d-M-Y', strtotime($row['created_at'])); ?></td>
                                        
                                    <?php elseif ($module === 'categories'): ?>
                                        <td class="py-3.5 px-2 font-bold">#<?php echo $row['id']; ?></td>
                                        <td class="py-3.5 px-2 font-bold text-slate-950"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="py-3.5 px-2"><code><?php echo htmlspecialchars($row['slug']); ?></code></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td class="py-3.5 px-2 text-slate-400"><?php echo date('d-M-Y', strtotime($row['created_at'])); ?></td>

                                    <?php elseif ($module === 'inquiries'): ?>
                                        <td class="py-3.5 px-2 font-bold">#<?php echo $row['id']; ?></td>
                                        <td class="py-3.5 px-2 font-bold text-slate-950"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['phone']); ?></td>
                                        <td class="py-3.5 px-2 italic max-w-xs break-words">"<?php echo htmlspecialchars($row['message']); ?>"</td>
                                        <td class="py-3.5 px-2 font-bold uppercase"><?php echo htmlspecialchars($row['status']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['agent_name'] ?? 'Unassigned'); ?></td>
                                        <td class="py-3.5 px-2 font-semibold uppercase"><?php echo htmlspecialchars($row['source']); ?></td>
                                        <td class="py-3.5 px-2 text-slate-500"><?php echo htmlspecialchars($row['campaign_name'] ?? 'N/A'); ?></td>
                                        <td class="py-3.5 px-2 text-slate-400"><?php echo date('d-M-Y H:i', strtotime($row['created_at'])); ?></td>

                                    <?php elseif ($module === 'meta_leads'): ?>
                                        <td class="py-3.5 px-2 font-bold">#<?php echo $row['id']; ?></td>
                                        <td class="py-3.5 px-2 font-bold text-slate-950"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['phone']); ?></td>
                                        <td class="py-3.5 px-2 font-bold text-slate-900"><?php echo htmlspecialchars($row['campaign_name'] ?? 'N/A'); ?></td>
                                        <td class="py-3.5 px-2 font-bold uppercase"><?php echo htmlspecialchars($row['status']); ?></td>
                                        <td class="py-3.5 px-2 text-slate-400"><?php echo date('d-M-Y H:i', strtotime($row['created_at'])); ?></td>

                                    <?php elseif ($module === 'users'): ?>
                                        <td class="py-3.5 px-2 font-bold">#<?php echo $row['id']; ?></td>
                                        <td class="py-3.5 px-2 font-bold text-slate-950"><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td class="py-3.5 px-2"><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td class="py-3.5 px-2 font-semibold"><?php echo htmlspecialchars($row['role_name']); ?></td>
                                        <td class="py-3.5 px-2 font-bold uppercase"><?php echo htmlspecialchars($row['status']); ?></td>
                                        <td class="py-3.5 px-2 text-slate-400"><?php echo date('d-M-Y', strtotime($row['created_at'])); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer details -->
            <div class="mt-12 pt-6 border-t border-slate-200 text-center text-[10px] text-slate-400 uppercase tracking-widest">
                End of Report. Confidential Workspace Document.
            </div>
        </div>
        
        <!-- Automatic print script -->
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => {
                    window.print();
                }, 800);
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
