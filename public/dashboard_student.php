<?php
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/controllers/InquiryController.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

session_start();
requireRole('student');

$studentId = (int) $_SESSION['user_id'];
$controller = new InquiryController();

$dbConn = getDbConnection();
$officeResult = $dbConn->query('SELECT office_id, office_name FROM offices WHERE is_active = 1 ORDER BY office_name');
$offices = $officeResult->fetch_all(MYSQLI_ASSOC);

$inquiries = $controller->listForStudent($studentId);

// Fetch recent staff replies grouped by office
$repliesQuery = "
    SELECT
        ir.response_id,
        ir.inquiry_id,
        ir.message,
        ir.created_at,
        i.subject,
        o.office_name,
        CONCAT(u.first_name, ' ', u.last_name) AS staff_name
    FROM inquiry_responses ir
    JOIN inquiries i ON ir.inquiry_id = i.inquiry_id
    JOIN offices o ON i.office_id = o.office_id
    JOIN users u ON ir.staff_id = u.user_id
    WHERE i.student_id = ?
    ORDER BY ir.created_at DESC
    LIMIT 10
";
$stmt = $dbConn->prepare($repliesQuery);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$repliesResult = $stmt->get_result();
$recentReplies = $repliesResult->fetch_all(MYSQLI_ASSOC);

// Group replies by office
$repliesByOffice = [];
foreach ($recentReplies as $reply) {
    $office = $reply['office_name'];
    if (!isset($repliesByOffice[$office])) {
        $repliesByOffice[$office] = [];
    }
    $repliesByOffice[$office][] = $reply;
}

$initials = '';
$nameParts = preg_split('/\s+/', trim((string) $_SESSION['name']));
foreach ($nameParts as $part) {
    if ($part !== '') {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
$initials = mb_substr($initials, 0, 2);
$firstName = $nameParts[0] ?? 'Student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Ask Ben - HELPDESKCRMC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
<style>
:root{
  --ink:#1c1b18;
  --ink-2:#26241f;
  --amber:#ecc94b;
  --amber-dk:#c98a06;
  --red:#b8231c;
  --teal:#1e7a8c;
  --cream:#fbf6ee;
  --card:#ffffff;
  --line:#ece3d6;
  --muted:#847c6e;
}
*{box-sizing:border-box;}
html,body{margin:0;max-width:100%;overflow-x:hidden;height:100%;}
body{
  font-family:'Inter',ui-sans-serif,system-ui,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;
  background:var(--cream);color:var(--ink);
  -webkit-font-smoothing:antialiased;
  text-rendering:optimizeLegibility;
  padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px);
}
h1,h3,h4{letter-spacing:-0.01em;margin:0;}
.icon{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none;display:block;}

.app{display:flex;align-items:stretch;height:100vh;}

/* Sidebar */
.sidebar{order:1;flex:0 0 230px;width:230px;background:#fdfcfa;color:var(--ink);padding:14px 12px;display:flex;flex-direction:column;min-width:0;overflow:hidden;border-right:1px solid var(--line);}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:16px;padding:0 6px;}
.brand img{height:28px;width:auto;flex:none;}
.brand-name{font-weight:700;color:var(--ink);font-size:14px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.brand-name span{color:var(--amber-dk);}
.brand-btn{width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#a89f8c;flex:none;cursor:pointer;}
.brand-btn .icon{width:14px;height:14px;}
.brand-btn:hover{background:#f1ede4;}

.nav-label{font-size:10px;font-weight:700;letter-spacing:.05em;color:#a89f8c;margin:10px 4px 6px;display:flex;align-items:center;justify-content:space-between;}
.nav-item{display:flex;align-items:center;gap:10px;padding:6px 8px;border-radius:8px;color:#5c5648;text-decoration:none;font-size:13px;margin-bottom:1px;cursor:pointer;}
.nav-item .icon{color:#9a9182;stroke:currentColor;}
.nav-item:hover{background:#f1ede4;}
.nav-item.active{color:var(--amber-dk);font-weight:600;background:#faf1dc;}
.nav-item.active .icon{color:var(--amber-dk);}

.group{margin-top:2px;margin-bottom:2px;}
.group-head{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:8px;background:#fdece9;margin-bottom:2px;}
.group-head .badge{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:700;color:#fff;flex:none;}
.group-head span{font-size:13px;font-weight:700;color:var(--ink);}
.channel{display:flex;align-items:center;gap:9px;padding:6px 8px 6px 30px;border-radius:8px;color:#7a7362;text-decoration:none;font-size:12.5px;margin-bottom:1px;cursor:pointer;}
.channel:hover{background:#f1ede4;}
.channel .icon{width:15px;height:15px;color:#a89f8c;}
.channel .snippet{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.channel .time{font-size:10px;color:#b0a996;font-weight:400;flex:none;}

.empty-replies{text-align:center;padding:14px 10px;color:#a89f8c;}
.empty-replies .icon{display:block;margin:0 auto 6px;}
.empty-replies p{margin:0 0 3px;font-size:11.5px;font-weight:600;}
.empty-replies span{display:block;font-size:10px;color:#c4bba6;line-height:1.35;}

.sidebar-spacer{flex:1;}
.sidebar-foot{border-top:1px solid var(--line);margin-top:6px;padding-top:6px;}
.sidebar-profile{display:flex;align-items:center;gap:9px;padding:6px 8px;border-radius:8px;cursor:pointer;}
.sidebar-profile:hover{background:#f1ede4;}
.avatar{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--amber),var(--amber-dk));color:var(--ink);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex:none;}
.user-name{font-size:12.5px;font-weight:700;color:var(--ink);}
.user-sub{font-size:10.5px;color:var(--muted);}
.urgent-card{background:#fff9ec;border:1px solid #f0e2bd;border-radius:12px;padding:10px;margin-top:8px;}
.urgent-card h4{margin:0 0 3px;font-size:11.5px;color:var(--ink);}
.urgent-card p{margin:0 0 7px;font-size:10.5px;line-height:1.35;color:var(--muted);}
.urgent-btn{display:inline-block;background:var(--amber-dk);color:#fff;font-size:11px;font-weight:700;padding:5px 12px;border-radius:16px;text-decoration:none;cursor:pointer;border:none;}

/* Main */
.main{order:2;flex:1 1 auto;position:relative;min-width:0;height:100vh;overflow:hidden;}
.main.with-padding{padding:10px 24px 10px;}
.blob{position:absolute;border-radius:50%;filter:blur(60px);opacity:.35;z-index:0;pointer-events:none;}
.blob-1{width:300px;height:300px;background:var(--amber);top:-120px;left:100px;}
.blob-2{width:260px;height:260px;background:var(--teal);top:20px;right:-90px;opacity:.25;}
.main > *:not(.blob){position:relative;z-index:1;}

#heroView{display:block;overflow-y:auto;height:100%;padding:10px 24px 20px;}
#concernsView{display:none;overflow-y:auto;height:100%;padding:10px 24px 20px;}
#chatView{position:absolute;top:0;left:0;right:0;bottom:0;z-index:10;background:var(--cream);flex-direction:column;display:none;}
#chatView.active{display:flex;}

.hero{text-align:center;padding:10px 0 12px;}
.hero-avatar{width:100px;height:100px;margin:0 auto 12px;background:transparent;display:flex;align-items:center;justify-content:center;overflow:visible;}
.hero-avatar img{width:100%;height:100%;object-fit:contain;}
.hero h1{font-size:24px;margin:0 0 4px;font-weight:700;}
.hero h1 b{color:var(--amber-dk);font-weight:800;}
.hero p{margin:0;color:var(--muted);font-size:14px;}
.hero p.sub{margin-top:2px;font-size:12.5px;color:#a49a86;}

.search-pill{max-width:520px;margin:14px auto 0;background:#fff;border-radius:999px;display:flex;align-items:center;gap:10px;padding:10px 18px;box-shadow:0 8px 20px -12px rgba(28,27,24,.15);border:1px solid var(--line);cursor:text;}
.search-pill input{border:none;outline:none;flex:1;font-size:14px;color:var(--ink);background:transparent;min-width:0;}
.search-pill .icon{color:#b0a996;width:19px;height:19px;}

.content{max-width:1040px;margin:0 auto;width:100%;}

.section-title{font-size:10.5px;font-weight:700;letter-spacing:.02em;color:#7a7362;margin:8px 0 5px;text-align:left;}

.general-card{background:var(--card);border-radius:14px;padding:9px 12px;max-width:320px;margin:0;box-shadow:0 10px 24px -16px rgba(28,27,24,.18);display:flex;gap:9px;align-items:flex-start;border:1px solid var(--line);cursor:pointer;transition:transform .15s;}
.general-card:hover{transform:translateY(-2px);}
.general-card .ic{width:28px;height:28px;border-radius:9px;background:linear-gradient(135deg,var(--amber),var(--amber-dk));display:flex;align-items:center;justify-content:center;flex:none;}
.general-card .ic .icon{stroke:var(--ink);width:15px;height:15px;}
.general-card h3{margin:0 0 2px;font-size:13px;}
.general-card p{margin:0;font-size:11px;color:var(--muted);}

.grid{display:flex;flex-wrap:wrap;gap:7px;justify-content:flex-start;}
.tile{background:linear-gradient(160deg,var(--ink-2),#141310);border-radius:12px;padding:9px 14px;color:#fff;display:flex;flex:0 0 auto;flex-direction:column;gap:4px;box-shadow:0 10px 20px -16px rgba(20,19,16,.55);border:1px solid rgba(255,255,255,.05);width:170px;white-space:normal;cursor:pointer;transition:transform .15s;}
.tile:hover{transform:translateY(-2px);}
.tile .ic{width:24px;height:24px;border-radius:8px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;color:var(--amber-dk);}
.tile .ic .icon{width:13px;height:13px;stroke:currentColor;}
.tile.alt .ic{color:var(--teal);}
.tile h4{margin:0;font-size:12px;font-weight:600;}
.tile p{margin:0;font-size:10px;color:#a79f8f;}

/* Right panel */
.panel{order:3;flex:0 0 210px;width:210px;background:#fff;border-left:1px solid var(--line);padding:16px 13px;min-width:0;overflow-y:auto;}
.panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.bell{margin-left:auto;width:28px;height:28px;border-radius:50%;background:#f6f1e8;display:flex;align-items:center;justify-content:center;color:#7a7362;}
.bell .icon{width:14px;height:14px;}
.panel h3{font-size:13px;margin:0;}
.search-mini{background:#f6f1e8;border-radius:10px;padding:7px 11px;display:flex;align-items:center;gap:7px;color:#a89f8c;font-size:11.5px;margin-bottom:12px;}
.empty-state{text-align:center;padding:18px 8px;color:#a89f8c;}
.empty-state .icon{width:24px;height:24px;margin:0 auto 7px;color:#c4bba6;}
.empty-state p{margin:0;font-size:11.5px;}
.empty-state span{display:block;font-size:10.5px;color:#c4bba6;margin-top:3px;}

.concern-row{padding:8px 10px;border-radius:8px;margin-bottom:6px;cursor:pointer;background:#fafaf9;border:1px solid var(--line);}
.concern-row:hover{background:#f5f4f0;}
.concern-title{font-size:11.5px;font-weight:600;color:var(--ink);margin-bottom:2px;}
.concern-meta{font-size:10px;color:var(--muted);}
.tag{display:inline-block;padding:2px 7px;border-radius:12px;font-size:9px;font-weight:700;text-transform:uppercase;}
.tag.pending{background:#FEF3C7;color:#92400E;}
.tag.inprogress{background:#DBEAFE;color:#1E40AF;}
.tag.resolved{background:#D1FAE5;color:#065F46;}

/* Concerns view */
#concernsView{display:none;}
.concerns-header{margin-bottom:22px;}
.concerns-header h1{font-size:24px;font-weight:800;margin:0 0 4px;}
.concerns-header p{margin:0;color:var(--muted);font-size:13.5px;}
.faq-card{background:var(--card);border-radius:14px;padding:16px;border:1px solid var(--line);}
.faq-row{padding:14px;border-radius:10px;background:#fafaf9;margin-bottom:8px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;}
.faq-row:hover{background:#f5f4f0;}
.faq-row .left{flex:1;min-width:0;}
.faq-row .subject{font-size:14px;font-weight:600;color:var(--ink);margin-bottom:4px;}
.faq-row .meta{font-size:11.5px;color:var(--muted);}
.faq-row .right{display:flex;align-items:center;gap:8px;}
.faq-row .chev{width:16px;height:16px;color:var(--muted);}
.concern-detail{padding:12px 14px;background:#f9f8f6;border-radius:8px;margin-top:6px;margin-bottom:8px;display:none;}
.faq-row.open + .concern-detail{display:block;}
.reply-box{padding:10px;background:#fff;border-radius:8px;border:1px solid var(--line);}
.reply-head{font-size:11px;font-weight:700;color:var(--amber-dk);margin-bottom:6px;}
.reply-msg{font-size:12px;color:var(--ink);line-height:1.5;}
.reply-empty{font-size:12px;color:var(--muted);font-style:italic;}

/* Ben conversation overlay */
.ben-overlay{display:none;position:fixed;inset:0;background:rgba(28,27,24,.6);z-index:999;align-items:center;justify-content:center;}
.ben-overlay.active{display:flex;}
.ben-modal{background:var(--cream);width:min(92%,580px);max-height:85vh;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);display:flex;flex-direction:column;overflow:hidden;}
.ben-header{padding:16px 20px;background:var(--card);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;}
.ben-header h2{font-size:16px;font-weight:700;margin:0;color:var(--ink);}
.ben-close{background:none;border:none;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted);}
.ben-close:hover{background:#f1ede4;}
.ben-close .icon{width:16px;height:16px;}
.ben-body{flex:1;overflow-y:auto;padding:16px 20px;}
.ben-thread{display:flex;flex-direction:column;gap:12px;}
.ben-msg{display:flex;gap:10px;align-items:flex-start;}
.ben-msg.student{flex-direction:row-reverse;}
.ben-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--amber),var(--amber-dk));display:flex;align-items:center;justify-content:center;flex:none;}
.ben-avatar .icon{width:18px;height:18px;stroke:var(--ink);}
.ben-bubble{background:var(--card);padding:10px 14px;border-radius:12px;border:1px solid var(--line);max-width:75%;font-size:13px;line-height:1.5;}
.ben-msg.student .ben-bubble{background:linear-gradient(135deg,var(--amber),var(--amber-dk));color:var(--ink);border:none;}
.ben-input-bar{padding:12px 16px;background:var(--card);border-top:1px solid var(--line);}
.ben-input-wrap{display:flex;gap:8px;align-items:center;}
.ben-input-wrap input{flex:1;padding:10px 14px;border:1px solid var(--line);border-radius:999px;font-size:13px;outline:none;}
.ben-send{background:var(--amber-dk);color:#fff;border:none;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;flex:none;}
.ben-send .icon{width:18px;height:18px;stroke:currentColor;}
.ben-send:disabled{background:#e5dcc8;cursor:not-allowed;}

/* Chat view */
.chat-header{display:flex;align-items:center;gap:12px;padding:12px 22px;border-bottom:1px solid var(--line);background:#fffdf9;flex:none;}
.back-btn{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#7a7362;flex:none;cursor:pointer;background:transparent;border:none;}
.back-btn:hover{background:#f1ede4;}
.chat-header .ic{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--amber),var(--amber-dk));display:flex;align-items:center;justify-content:center;flex:none;}
.chat-header .ic .icon{stroke:var(--ink);width:17px;height:17px;}
.chat-header h2{margin:0;font-size:14.5px;}
.chat-header p{margin:1px 0 0;font-size:11.5px;color:var(--muted);}
.status-pill{margin-left:auto;display:flex;align-items:center;gap:6px;background:#eaf7ee;color:#1e7a44;font-size:11px;font-weight:700;padding:5px 12px;border-radius:999px;flex:none;}
.status-dot{width:6px;height:6px;border-radius:50%;background:#1e7a44;}

.chat-thread{flex:1;overflow-y:auto;padding:22px 0 10px;background:var(--cream);}
.thread-inner{max-width:640px;margin:0 auto;padding:0 24px;display:flex;flex-direction:column;gap:16px;}

.day-divider{text-align:center;font-size:10.5px;color:#b0a996;margin:2px 0 4px;position:relative;}

.msg{display:flex;gap:10px;max-width:82%;}
.msg .m-avatar{width:32px;height:32px;border-radius:9px;flex:none;display:flex;align-items:center;justify-content:center;overflow:visible;background:transparent;padding:2px;}
.msg .m-avatar img{width:100%;height:100%;object-fit:contain;}
.msg .m-avatar .icon{width:15px;height:15px;}
.msg.ben .m-avatar{background:transparent;box-shadow:none;}
.msg .bubble-wrap{display:flex;flex-direction:column;gap:3px;}
.msg .bubble{border-radius:14px;padding:10px 13px;font-size:13px;line-height:1.5;}
.msg.ben .bubble{background:#fff;border:1px solid var(--line);border-top-left-radius:4px;box-shadow:0 8px 18px -14px rgba(28,27,24,.15);}
.msg .name{font-size:11px;font-weight:700;color:var(--muted);margin-left:2px;}
.msg .time{font-size:10px;color:#b0a996;margin-left:2px;}
.msg.user{align-self:flex-end;flex-direction:row-reverse;}
.msg.user .m-avatar{background:linear-gradient(135deg,var(--amber),var(--amber-dk));color:var(--ink);font-size:10.5px;font-weight:700;}
.msg.user .bubble{background:linear-gradient(135deg,var(--amber),var(--amber-dk));color:var(--ink);border-top-right-radius:4px;font-weight:500;}
.msg.user .bubble-wrap{align-items:flex-end;}
.msg.user .time{margin-right:2px;}

.info-card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:11px 13px;font-size:12px;max-width:82%;box-shadow:0 8px 18px -14px rgba(28,27,24,.15);}
.info-card .row{display:flex;align-items:center;gap:8px;margin-bottom:6px;}
.info-card .row:last-child{margin-bottom:0;}
.info-card .lbl{color:var(--muted);width:96px;flex:none;}
.info-card .val{font-weight:600;}
.info-card .head{display:flex;align-items:center;gap:8px;font-weight:700;margin-bottom:8px;color:var(--ink);font-size:12.5px;}
.info-card .head .icon{width:15px;height:15px;color:#1e7a44;}

.typing{display:flex;gap:4px;align-items:center;padding:10px 13px;}
.typing span{width:5px;height:5px;border-radius:50%;background:#c7bfae;display:inline-block;animation:typing-bounce 1.4s infinite;}
.typing span:nth-child(2){animation-delay:.2s;}
.typing span:nth-child(3){animation-delay:.4s;}
@keyframes typing-bounce{0%,60%,100%{opacity:.3;transform:translateY(0)}30%{opacity:1;transform:translateY(-6px)}}

.composer-wrap{flex:none;padding:12px 24px 18px;background:var(--cream);}
.composer{max-width:640px;margin:0 auto;background:#fff;border-radius:22px;border:1px solid var(--line);box-shadow:0 10px 24px -16px rgba(28,27,24,.18);display:flex;align-items:center;gap:6px;padding:8px 8px 8px 16px;}
.composer input{border:none;outline:none;flex:1;font-size:13.5px;color:var(--ink);background:transparent;min-width:0;}
.composer input::placeholder{color:#b0a996;}
.attach-btn{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#a89f8c;flex:none;background:transparent;border:none;cursor:pointer;}
.attach-btn:hover{background:#f1ede4;}
.send-btn{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--amber),var(--amber-dk));display:flex;align-items:center;justify-content:center;flex:none;box-shadow:0 8px 16px -8px rgba(201,138,6,.5);cursor:pointer;border:none;}
.send-btn .icon{stroke:var(--ink);width:16px;height:16px;}
.send-btn:disabled{opacity:.5;cursor:not-allowed;}
.composer-hint{text-align:center;font-size:10px;color:#b0a996;margin-top:8px;}

.history-item{display:flex;align-items:center;gap:9px;padding:8px;border-radius:10px;text-decoration:none;cursor:pointer;}
.history-item.active{background:#faf1dc;}
.history-item .ic{width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,var(--amber),var(--amber-dk));display:flex;align-items:center;justify-content:center;flex:none;}
.history-item .ic .icon{stroke:var(--ink);width:15px;height:15px;}
.h-title{font-size:12.5px;font-weight:700;color:var(--ink);}
.h-sub{font-size:10.5px;color:var(--muted);}


@media (max-width:960px){
  .app{flex-direction:column;height:auto;}
  .main{height:auto;min-height:100vh;}
  .sidebar,.panel{display:none;}
}
</style>
</head>
<body>

<svg style="display:none" aria-hidden="true">
<defs>
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9a1 1 0 0 0 1 1H10v-6h4v6h3.5a1 1 0 0 0 1-1v-9"/></symbol>
  <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v5h5"/><path d="M8.5 13h7M8.5 17h7"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c1.2-3.6 4-5.5 7-5.5s5.8 1.9 7 5.5"/></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 4H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3"/><path d="M14 8l4 4-4 4"/><path d="M18 12H9"/></symbol>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></symbol>
  <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 10a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10Z"/><path d="M10 19a2 2 0 0 0 4 0"/></symbol>
  <symbol id="i-folder" viewBox="0 0 24 24"><path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z"/></symbol>
  <symbol id="i-dollar" viewBox="0 0 24 24"><path d="M12 2v20"/><path d="M17 6.5c0-1.8-2-3-5-3s-5 1.4-5 3.2 2 2.8 5 3.3 5 1.5 5 3.3-2 3.2-5 3.2-5-1.2-5-3"/></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3.5 19c1-3 3-4.5 5.5-4.5s4.5 1.5 5.5 4.5"/><circle cx="17" cy="8.5" r="2.3"/><path d="M15.5 14.2c2.2.4 3.5 1.8 4.4 4.3"/></symbol>
  <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><path d="M12 7.5v.01"/></symbol>
  <symbol id="i-book" viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H12v18H6.5A2.5 2.5 0 0 1 4 18.5Z"/><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H12v18h5.5a2.5 2.5 0 0 0 2.5-2.5Z"/></symbol>
  <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12 20 3"/><path d="M16 7l3 3"/><path d="M13 10l2.5 2.5"/></symbol>
  <symbol id="i-cross" viewBox="0 0 24 24"><path d="M12 4v16M4 12h16" stroke-width="3"/></symbol>
  <symbol id="i-monitor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="1.5"/><path d="M9 21h6M12 17v4"/></symbol>
  <symbol id="i-briefcase" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="12" rx="1.5"/><path d="M8 8V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></symbol>
  <symbol id="i-code" viewBox="0 0 24 24"><path d="m9 8-4 4 4 4"/><path d="m15 8 4 4-4 4"/></symbol>
  <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V10M12 20V4M20 20v-7"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 5-3 8-7 9-4-1-7-4-7-9V6Z"/></symbol>
  <symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.8 1.8 0 0 0 .3 1.9l.1.1a2.1 2.1 0 1 1-3 3l-.1-.1a1.8 1.8 0 0 0-1.9-.3 1.8 1.8 0 0 0-1.1 1.6V21a2.1 2.1 0 1 1-4.2 0v-.1a1.8 1.8 0 0 0-1.1-1.6 1.8 1.8 0 0 0-1.9.3l-.1.1a2.1 2.1 0 1 1-3-3l.1-.1a1.8 1.8 0 0 0 .3-1.9 1.8 1.8 0 0 0-1.6-1.1H2.9a2.1 2.1 0 1 1 0-4.2H3a1.8 1.8 0 0 0 1.6-1.1 1.8 1.8 0 0 0-.3-1.9l-.1-.1a2.1 2.1 0 1 1 3-3l.1.1a1.8 1.8 0 0 0 1.9.3H9.3A1.8 1.8 0 0 0 10.4 3V2.9a2.1 2.1 0 1 1 4.2 0V3a1.8 1.8 0 0 0 1.1 1.6 1.8 1.8 0 0 0 1.9-.3l.1-.1a2.1 2.1 0 1 1 3 3l-.1.1a1.8 1.8 0 0 0-.3 1.9v.1a1.8 1.8 0 0 0 1.6 1.1h.1a2.1 2.1 0 1 1 0 4.2H21a1.8 1.8 0 0 0-1.6 1.1Z"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></symbol>
  <symbol id="i-send" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4 20-7z"/></symbol>
  <symbol id="i-back" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></symbol>
  <symbol id="i-arrow-up" viewBox="0 0 24 24"><path d="M12 19V5"/><path d="M6 11l6-6 6 6"/></symbol>
  <symbol id="i-paperclip" viewBox="0 0 24 24"><path d="M8 12.5l6-6a3 3 0 0 1 4.2 4.2l-8 8a5 5 0 1 1-7-7l7-7"/></symbol>
  <symbol id="i-reply" viewBox="0 0 24 24"><path d="M9 8 4 12l5 4"/><path d="M4 12h9a6 6 0 0 1 6 6v1"/></symbol>
  <symbol id="i-hash" viewBox="0 0 24 24"><path d="M9 4 7 20M17 4l-2 16M4 9h16M3.5 15h16"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></symbol>
  <symbol id="i-updown" viewBox="0 0 24 24"><path d="M8 9l4-4 4 4"/><path d="M16 15l-4 4-4-4"/></symbol>
  <symbol id="i-collapse" viewBox="0 0 24 24"><path d="M14 6l-6 6 6 6"/><path d="M19 6l-6 6 6 6"/></symbol>
</defs>
</svg>

<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <img src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC">
      <div class="brand-name">Helpdesk<span>CRMC</span></div>
    </div>

    <a class="nav-item active" id="navAskBen"><svg class="icon"><use href="#i-home"/></svg> Ask Ben</a>
    <a class="nav-item" id="navMyConcerns"><svg class="icon"><use href="#i-file"/></svg> My Concerns</a>
    <a class="nav-item" id="navProfile"><svg class="icon"><use href="#i-user"/></svg> Profile</a>

    <div class="nav-label">
      <span>REPLIES FROM STAFF</span>
    </div>

    <?php if (!empty($repliesByOffice)): ?>
      <?php foreach (array_slice($repliesByOffice, 0, 2) as $officeName => $replies): ?>
        <?php
          $officeInitial = mb_strtoupper(mb_substr($officeName, 0, 1));
          $colors = [
            'Registrar' => ['#b8231c', '#8f1912'],
            'SASO' => ['#ecc94b', '#c98a06'],
            'Finance' => ['#1e7a8c', '#155e6d'],
          ];
          $gradient = $colors[$officeName] ?? ['#847c6e', '#5c5648'];
          $latestReply = $replies[0];
          $timeAgo = '';
          $diff = time() - strtotime($latestReply['created_at']);
          if ($diff < 3600) $timeAgo = floor($diff / 60) . 'm';
          elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h';
          else $timeAgo = floor($diff / 86400) . 'd';
        ?>
        <div class="group">
          <div class="group-head">
            <div class="badge" style="background:linear-gradient(135deg,<?= $gradient[0] ?>,<?= $gradient[1] ?>);"><?= htmlspecialchars($officeInitial) ?></div>
            <span><?= htmlspecialchars($officeName) ?></span>
          </div>
          <a class="channel" data-inquiry-id="<?= $latestReply['inquiry_id'] ?>">
            <svg class="icon" style="width:15px;height:15px;"><use href="#i-chat"/></svg>
            <span class="snippet"><?= htmlspecialchars(mb_substr($latestReply['subject'], 0, 25)) ?></span>
            <span class="time"><?= htmlspecialchars($timeAgo) ?></span>
          </a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-replies">
        <svg class="icon" style="width:20px;height:20px;color:#c4bba6;margin:0 auto 6px;"><use href="#i-chat"/></svg>
        <p>No replies yet</p>
        <span>Staff will respond to your concerns within 2-3 working days</span>
      </div>
    <?php endif; ?>

    <div class="sidebar-spacer"></div>

    <div class="urgent-card">
      <h4>Need urgent help?</h4>
      <p>Walk-in concerns are still welcome at the Student Affairs office.</p>
      <button class="urgent-btn">Visit SASO</button>
    </div>

    <a href="logout.php" class="nav-item" style="margin-top:8px;"><svg class="icon"><use href="#i-logout"/></svg> Logout</a>

    <div class="sidebar-foot">
      <a class="nav-item"><svg class="icon"><use href="#i-gear"/></svg> Settings</a>
      <div class="sidebar-profile">
        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
          <div class="user-sub">BSIT · 3rd Year</div>
        </div>
      </div>
    </div>
  </aside>

  <main class="main" id="mainView">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div id="heroView">
      <div class="hero">
        <div class="hero-avatar"><img src="assets/images/ben-model.png" alt="Ben"></div>
        <h1>Good morning, <?= htmlspecialchars($firstName) ?>. I'm <b>Ben</b></h1>
        <p>I'm here to help you with your concern.</p>
        <p class="sub">Choose a category below to get started</p>
        <div class="search-pill" id="heroSearchPill">
          <svg class="icon"><use href="#i-search"/></svg>
          <input placeholder="Type your concern, e.g. 'I lost my student ID'" id="heroSearchInput" />
        </div>
      </div>

      <div class="content">
        <div class="section-title">GENERAL</div>
        <div class="general-card" data-category="General">
          <div class="ic"><svg class="icon"><use href="#i-chat"/></svg></div>
          <div>
            <h3>General Inquiry</h3>
            <p>Ask a concern directly to Ben — no category needed.</p>
          </div>
        </div>

        <div class="section-title">OFFICES</div>
        <div class="grid">
          <div class="tile" data-category="Registrar"><div class="ic"><svg class="icon"><use href="#i-folder"/></svg></div><h4>Registrar</h4><p>Enrollment, records, IDs</p></div>
          <div class="tile alt" data-category="Finance"><div class="ic"><svg class="icon"><use href="#i-dollar"/></svg></div><h4>Finance</h4><p>Fees, payments, receipts</p></div>
          <div class="tile" data-category="SASO"><div class="ic"><svg class="icon"><use href="#i-users"/></svg></div><h4>SASO</h4><p>Student affairs & orgs</p></div>
          <div class="tile alt" data-category="Guidance"><div class="ic"><svg class="icon"><use href="#i-info"/></svg></div><h4>Guidance</h4><p>Counseling & support</p></div>
          <div class="tile" data-category="Library"><div class="ic"><svg class="icon"><use href="#i-book"/></svg></div><h4>Library</h4><p>Books, fines, access</p></div>
          <div class="tile alt" data-category="Property Custodian"><div class="ic"><svg class="icon"><use href="#i-key"/></svg></div><h4>Property Custodian</h4><p>Facilities & equipment</p></div>
          <div class="tile" data-category="Clinic"><div class="ic"><svg class="icon"><use href="#i-cross"/></svg></div><h4>Clinic</h4><p>Medical certificates</p></div>
          <div class="tile alt" data-category="ITCD"><div class="ic"><svg class="icon"><use href="#i-monitor"/></svg></div><h4>ITCD</h4><p>Portal & IT support</p></div>
          <div class="tile" data-category="Human Resources"><div class="ic"><svg class="icon"><use href="#i-briefcase"/></svg></div><h4>Human Resources</h4><p>Employment inquiries</p></div>
        </div>

        <div class="section-title">DEPARTMENTS</div>
        <div class="grid">
          <div class="tile" data-category="CCS"><div class="ic"><svg class="icon"><use href="#i-code"/></svg></div><h4>CCS</h4><p>Computer science dept</p></div>
          <div class="tile alt" data-category="CBE"><div class="ic"><svg class="icon"><use href="#i-chart"/></svg></div><h4>CBE</h4><p>Business education dept</p></div>
          <div class="tile" data-category="CTE"><div class="ic"><svg class="icon"><use href="#i-book"/></svg></div><h4>CTE</h4><p>Teacher education dept</p></div>
          <div class="tile alt" data-category="CCJE"><div class="ic"><svg class="icon"><use href="#i-shield"/></svg></div><h4>CCJE</h4><p>Criminal justice dept</p></div>
        </div>
      </div>
    </div>

    <div id="concernsView" class="concerns-header" style="display:none;">
      <h1>My Concerns</h1>
      <p>Everything you've submitted or asked Ben about, including replies from staff.</p>
      <div class="faq-card" id="concernsListContainer"></div>
    </div>

    <div id="chatView">
      <div class="chat-header">
        <button class="back-btn" id="backToDashboard"><svg class="icon"><use href="#i-back"/></svg></button>
        <div class="ic" id="chatCategoryIcon"><svg class="icon"><use href="#i-chat"/></svg></div>
        <div>
          <h2 id="chatCategoryName">General</h2>
          <p id="chatCategoryDesc">Ask your concern</p>
        </div>
      </div>

      <div class="chat-thread">
        <div class="thread-inner" id="chatThreadInner">
          <div class="day-divider" id="chatDayDivider">Today</div>
        </div>
      </div>

      <div class="composer-wrap">
        <div class="composer">
          <button class="attach-btn" id="chatAttachBtn"><svg class="icon"><use href="#i-paperclip"/></svg></button>
          <input type="text" placeholder="Message Ben…" id="chatInput" />
          <button class="send-btn" id="chatSendBtn"><svg class="icon"><use href="#i-arrow-up"/></svg></button>
        </div>
        <div class="composer-hint">Ben can make mistakes. For urgent concerns, visit the office directly.</div>
      </div>
    </div>
  </main>

  <aside class="panel">
    <div class="panel-head">
      <h3>Chat history</h3>
      <div class="bell"><svg class="icon"><use href="#i-bell"/></svg></div>
    </div>
    <div class="search-mini">
      <svg class="icon"><use href="#i-search"/></svg>
      Search conversations…
    </div>
    <div id="chatHistoryList"></div>
  </aside>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

function escapeHtml(str) {
  if (typeof str !== 'string') return '';
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

function renderMarkdown(text) {
  if (!text) return '';
  let html = escapeHtml(text);
  html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/(?:^|\n)(\d+)\.\s+(.*?)(?=\n|$)/g, '<div style="margin-left:14px;text-indent:-14px;"><strong>$1.</strong> $2</div>');
  html = html.replace(/(?:^|\n)[-]\s+(.*?)(?=\n|$)/g, '<div style="margin-left:14px;text-indent:-10px;">• $1</div>');
  html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
  html = html.replace(/\n\n/g, '<div style="height:8px;"></div>');
  html = html.replace(/\n/g, '<br>');
  return html;
}

let concerns = <?= json_encode(array_map(function($inq) {
    return [
        'id' => $inq['inquiry_id'],
        'subject' => $inq['subject'] ?? mb_substr($inq['description'], 0, 60),
        'office' => $inq['office_name'] ?? 'General',
        'status' => strtolower(str_replace(' ', '', $inq['status'])),
        'date' => date('M j, Y', strtotime($inq['created_at'])),
        'reply' => null
    ];
}, $inquiries)) ?>;

let currentCategory = null;
let conversationHistory = [];

const categoryMeta = {
  'General': { icon: 'i-chat', desc: 'Ask your concern' },
  'Registrar': { icon: 'i-folder', desc: 'Enrollment, records, IDs' },
  'Finance': { icon: 'i-dollar', desc: 'Fees, payments, receipts' },
  'SASO': { icon: 'i-users', desc: 'Student affairs & orgs' },
  'Guidance': { icon: 'i-info', desc: 'Counseling & support' },
  'Library': { icon: 'i-book', desc: 'Books, fines, access' },
  'Property Custodian': { icon: 'i-key', desc: 'Facilities & equipment' },
  'Clinic': { icon: 'i-cross', desc: 'Medical certificates' },
  'ITCD': { icon: 'i-monitor', desc: 'Portal & IT support' },
  'Human Resources': { icon: 'i-briefcase', desc: 'Employment inquiries' },
  'CCS': { icon: 'i-code', desc: 'Computer science dept' },
  'CBE': { icon: 'i-chart', desc: 'Business education dept' },
  'CTE': { icon: 'i-book', desc: 'Teacher education dept' },
  'CCJE': { icon: 'i-shield', desc: 'Criminal justice dept' }
};

// Nav switching
document.getElementById('navAskBen').addEventListener('click', e => {
  e.preventDefault();
  showHeroView();
});

document.getElementById('navMyConcerns').addEventListener('click', e => {
  e.preventDefault();
  showConcernsView();
});

function showHeroView() {
  document.getElementById('heroView').style.display = 'block';
  document.getElementById('concernsView').style.display = 'none';
  const chatView = document.getElementById('chatView');
  chatView.classList.remove('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('navAskBen').classList.add('active');
}

function showConcernsView() {
  document.getElementById('heroView').style.display = 'none';
  document.getElementById('concernsView').style.display = 'block';
  document.getElementById('chatView').classList.remove('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('navMyConcerns').classList.add('active');
  renderConcernsList();
}

function showChatView(category) {
  document.getElementById('heroView').style.display = 'none';
  document.getElementById('concernsView').style.display = 'none';
  const chatView = document.getElementById('chatView');
  chatView.classList.add('active');

  currentCategory = category;
  conversationHistory = [];

  const meta = categoryMeta[category] || { icon: 'i-chat', desc: 'Ask your concern' };
  document.getElementById('chatCategoryName').textContent = category;
  document.getElementById('chatCategoryDesc').textContent = meta.desc;
  document.getElementById('chatCategoryIcon').innerHTML = `<svg class="icon"><use href="#${meta.icon}"/></svg>`;

  const threadInner = document.getElementById('chatThreadInner');
  threadInner.innerHTML = '';

  // Day divider
  const now = new Date();
  const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  const dayDiv = document.createElement('div');
  dayDiv.className = 'day-divider';
  dayDiv.textContent = `Today · ${timeStr}`;
  threadInner.appendChild(dayDiv);

  // Initial Ben greeting
  addBenMessage(`Hi <?= htmlspecialchars($firstName) ?>! I see you'd like help with <b>${escapeHtml(category)}</b> concerns. What can I help you with today?`);

  document.getElementById('chatInput').focus();
}

function addBenMessage(html, showTyping = false, expression = 'happy') {
  const threadInner = document.getElementById('chatThreadInner');
  const msg = document.createElement('div');
  msg.className = 'msg ben';

  const now = new Date();
  const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

  msg.innerHTML = `
    <div class="m-avatar">
      <img src="assets/images/ben-model.png" alt="Ben">
    </div>
    <div class="bubble-wrap">
      <span class="name">Ben</span>
      <div class="bubble">${html}</div>
      <span class="time">${timeStr}</span>
    </div>`;

  threadInner.appendChild(msg);
  scrollChatToBottom();

  if (showTyping) {
    addTypingIndicator();
  }
}

function addUserMessage(text) {
  const threadInner = document.getElementById('chatThreadInner');
  const msg = document.createElement('div');
  msg.className = 'msg user';

  const now = new Date();
  const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

  msg.innerHTML = `
    <div class="m-avatar"><?= htmlspecialchars($initials) ?></div>
    <div class="bubble-wrap">
      <div class="bubble">${escapeHtml(text)}</div>
      <span class="time">${timeStr}</span>
    </div>`;

  threadInner.appendChild(msg);
  scrollChatToBottom();
  conversationHistory.push({ role: 'student', message: text });
}

function addTypingIndicator() {
  const threadInner = document.getElementById('chatThreadInner');
  const msg = document.createElement('div');
  msg.className = 'msg ben';
  msg.id = 'typingIndicator';

  msg.innerHTML = `
    <div class="m-avatar">
      <img src="assets/images/ben-model.png" alt="Ben">
    </div>
    <div class="bubble-wrap">
      <div class="bubble typing"><span></span><span></span><span></span></div>
    </div>`;

  threadInner.appendChild(msg);
  scrollChatToBottom();
}

function removeTypingIndicator() {
  const typing = document.getElementById('typingIndicator');
  if (typing) typing.remove();
}

function scrollChatToBottom() {
  const chatThread = document.querySelector('.chat-thread');
  if (chatThread) {
    chatThread.scrollTop = chatThread.scrollHeight;
  }
}

// Back button
document.getElementById('backToDashboard').addEventListener('click', () => {
  showHeroView();
});

// Send message
document.getElementById('chatSendBtn').addEventListener('click', sendChatMessage);
document.getElementById('chatInput').addEventListener('keypress', e => {
  if (e.key === 'Enter') sendChatMessage();
});

async function sendChatMessage() {
  const input = document.getElementById('chatInput');
  const text = input.value.trim();
  if (!text) return;

  addUserMessage(text);
  input.value = '';

  // Add to chat history in right panel
  const existingIdx = concerns.findIndex(c => c.office === currentCategory && c.isLocal);
  if (existingIdx !== -1) {
    concerns[existingIdx].subject = text;
    concerns[existingIdx].date = 'Just now';
  } else {
    concerns.unshift({
      id: 'local_' + Date.now(),
      subject: text,
      office: currentCategory || 'General',
      status: 'active',
      date: 'Just now',
      isLocal: true
    });
  }
  renderChatHistory();

  addTypingIndicator();

  try {
    const res = await fetch('api/ai_chat.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN
      },
      body: JSON.stringify({
        message: text,
        category: currentCategory,
        history: conversationHistory
      })
    });

    const data = await res.json();
    removeTypingIndicator();

    if (data.success && data.answer) {
      addBenMessage(renderMarkdown(data.answer));
      conversationHistory.push({ role: 'assistant', message: data.answer });
    } else {
      addBenMessage("I'm having trouble processing that right now. Could you try rephrasing your concern?");
    }
  } catch (err) {
    console.error(err);
    removeTypingIndicator();
    addBenMessage("Sorry, I'm having connection issues. Please try again in a moment.");
  }
}

// Render concerns list
function renderConcernsList() {
  const container = document.getElementById('concernsListContainer');
  if (!concerns || concerns.length === 0) {
    container.innerHTML = '<div class="reply-empty">No concerns submitted yet.</div>';
    return;
  }

  container.innerHTML = '';
  concerns.forEach(item => {
    const wrap = document.createElement('div');
    const row = document.createElement('div');
    row.className = 'faq-row';
    const statusClass = item.status === 'pending' ? 'pending' : item.status === 'inprogress' ? 'inprogress' : 'resolved';
    const statusLabel = item.status === 'pending' ? 'Pending' : item.status === 'inprogress' ? 'In Progress' : 'Resolved';

    row.innerHTML = `
      <div class="left">
        <div class="subject">${escapeHtml(item.subject)}</div>
        <div class="meta">${escapeHtml(item.office)} · Submitted ${escapeHtml(item.date)}</div>
      </div>
      <div class="right">
        <span class="tag ${statusClass}">${statusLabel}</span>
        <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
      </div>`;

    const detail = document.createElement('div');
    detail.className = 'concern-detail';
    detail.innerHTML = item.reply
      ? `<div class="reply-box"><div class="reply-head">${escapeHtml(item.reply.from)} · ${escapeHtml(item.reply.date)}</div><div class="reply-msg">${escapeHtml(item.reply.message)}</div></div>`
      : `<div class="reply-empty">No reply yet — staff at ${escapeHtml(item.office)} typically respond within 2–3 working days.</div>`;

    row.addEventListener('click', () => {
      const open = row.classList.contains('open');
      row.classList.toggle('open');
    });

    wrap.appendChild(row);
    wrap.appendChild(detail);
    container.appendChild(wrap);
  });
}

// Render chat history in right panel
function renderChatHistory() {
  const container = document.getElementById('chatHistoryList');
  if (!concerns || concerns.length === 0) {
    container.innerHTML = '<div class="empty-state"><svg class="icon"><use href="#i-chat"/></svg><p>No conversations yet</p><span>Ask Ben something to start one</span></div>';
    return;
  }

  container.innerHTML = '';
  concerns.slice(0, 5).forEach(item => {
    const meta = categoryMeta[item.office] || { icon: 'i-chat', desc: '' };
    const row = document.createElement('a');
    row.className = 'history-item';
    row.href = '#';

    row.innerHTML = `
      <div class="ic"><svg class="icon"><use href="#${meta.icon}"/></svg></div>
      <div>
        <div class="h-title">${escapeHtml(item.office)}</div>
        <div class="h-sub">${escapeHtml(item.subject.substring(0, 25))}… · ${escapeHtml(item.date)}</div>
      </div>`;

    row.addEventListener('click', e => {
      e.preventDefault();
      showChatView(item.office);
    });

    container.appendChild(row);
  });
}

// Category tile clicks
document.querySelectorAll('[data-category]').forEach(el => {
  el.addEventListener('click', () => {
    const category = el.getAttribute('data-category');
    showChatView(category);
  });
});

// Hero search
document.getElementById('heroSearchInput').addEventListener('keypress', e => {
  if (e.key === 'Enter') {
    const text = e.target.value.trim();
    if (text) {
      showChatView('General');
      setTimeout(() => {
        document.getElementById('chatInput').value = text;
        sendChatMessage();
      }, 300);
    }
  }
});

document.getElementById('heroSearchPill').addEventListener('click', () => {
  document.getElementById('heroSearchInput').focus();
});

// Initialize
renderChatHistory();
</script>
</body>
</html>
