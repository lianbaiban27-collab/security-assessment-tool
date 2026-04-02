<?php
session_start();

$admin_password = $_ENV['ADMIN_PASSWORD'] ?? $_SERVER['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: 'security-tool-0627';

// ─── ログイン処理 ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_auth'] = true;
    } else {
        $login_error = 'パスワードが正しくありません';
    }
}
if (isset($_POST['logout'])) {
    $_SESSION['admin_auth'] = false;
    session_destroy();
}

$is_auth = !empty($_SESSION['admin_auth']);

// ─── Supabase からデータ取得 ─────────────────────────────
$submissions = [];
$fetch_error = null;
if ($is_auth) {
    $supabase_url = $_ENV['SUPABASE_URL'] ?? $_SERVER['SUPABASE_URL'] ?? getenv('SUPABASE_URL');
    $supabase_key = $_ENV['SUPABASE_ANON_KEY'] ?? $_SERVER['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY');
    if ($supabase_url && $supabase_key) {
        $url = $supabase_url . '/rest/v1/submissions?select=*&order=created_at.desc';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'apikey: ' . $supabase_key,
                'Authorization: Bearer ' . $supabase_key,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) {
            $submissions = json_decode($resp, true) ?? [];
        } else {
            $fetch_error = 'データ取得エラー (HTTP ' . $code . ')';
        }
    } else {
        $fetch_error = 'Supabase 環境変数が未設定です（SUPABASE_URL / SUPABASE_ANON_KEY）';
    }
}

// ─── ラベルマップ ────────────────────────────────────────
$industry_labels = [
    'finance' => '金融・保険', 'medical' => '医療・ヘルスケア',
    'manufacturing' => '製造業', 'it' => 'IT・通信',
    'retail' => '小売・EC', 'construction' => '建設・不動産',
    'service' => 'サービス業', 'government' => '官公庁・自治体',
    'education' => '教育', 'other' => 'その他',
];
$ec_labels = [
    'small' => '1〜29名', 'medium' => '30〜299名',
    'large' => '300〜999名', 'enterprise' => '1,000名以上',
];
$question_texts = [
    'Q01' => 'ファイアウォール（UTM）を導入し、定期的に更新していますか？',
    'Q02' => '不正通信・C2サーバへの通信遮断対策を実施していますか？',
    'Q03' => 'VPNを利用したリモートアクセス管理をしていますか？',
    'Q04' => 'Wi-Fiは社員用と来客用でネットワークを分離していますか？',
    'Q05' => 'ネットワークの通信ログを定期的に確認していますか？',
    'Q06' => '社内ネットワークにアクセス可能な端末を制限していますか？',
    'Q07' => 'ウイルス対策ソフト（EDR含む）を全端末に導入していますか？',
    'Q08' => 'OSやソフトウェアのセキュリティパッチを定期的に適用していますか？',
    'Q09' => 'PC・モバイル端末の暗号化・リモートワイプ対策をしていますか？',
    'Q10' => '私物デバイスの業務利用（BYOD）を制限・管理していますか？',
    'Q11' => '重要データのバックアップを日次以上の頻度で取得していますか？',
    'Q12' => 'バックアップデータをオフサイト（別拠点・クラウド）に保存していますか？',
    'Q13' => 'バックアップからの復旧テストを定期的に実施していますか？',
    'Q14' => '重要ファイルへのアクセス権限を必要最小限に設定していますか？',
    'Q15' => 'ファイルへのアクセスログを定期的に確認していますか？',
    'Q16' => '多要素認証（MFA）を主要システム・メールに導入していますか？',
    'Q17' => '退職者・異動者のアカウント削除・権限変更を即日対応していますか？',
    'Q18' => '特権アカウント（管理者権限）の使用を必要時のみに制限していますか？',
    'Q19' => 'パスワードポリシー（複雑性・定期変更）を定めていますか？',
    'Q20' => '不正ログインを検知できる仕組みを導入していますか？',
    'Q21' => '従業員向けセキュリティ教育を年1回以上実施していますか？',
    'Q22' => 'フィッシングメール訓練を実施したことがありますか？',
    'Q23' => 'サイバー攻撃を受けた場合のインシデント対応手順を文書化していますか？',
    'Q24' => 'セキュリティに関する社内規程を整備していますか？',
    'Q25' => 'セキュリティ対策の責任者を定めていますか？',
    'Q26' => '社内のセキュリティ対策状況を定期的に評価していますか？',
];
$categories_order = [
    'network'    => ['🛡️', 'ネットワーク・境界防御',    ['Q01','Q02','Q03','Q04','Q05','Q06']],
    'endpoint'   => ['💻', 'エンドポイント対策',          ['Q07','Q08','Q09','Q10']],
    'data'       => ['💾', 'データ管理・バックアップ',    ['Q11','Q12','Q13','Q14','Q15']],
    'access'     => ['🔑', 'アクセス管理・認証',          ['Q16','Q17','Q18','Q19','Q20']],
    'incident'   => ['📋', 'インシデント対応・教育',      ['Q21','Q22','Q23']],
    'governance' => ['🏢', '組織・ルール整備',            ['Q24','Q25','Q26']],
];
$answer_labels = ['yes' => '◎ はい', 'unknown' => '△ 不明', 'no' => '✗ いいえ'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理画面 | セキュリティ診断ツール</title>
<style>
:root {
    --bg:#f4f6f9; --white:#fff; --border:#dee2e6;
    --primary:#2563eb; --danger:#dc3545; --success:#198754; --warn:#f57c00;
    --text:#1a1a2e; --muted:#6c757d;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:'Helvetica Neue',Arial,'Hiragino Kaku Gothic ProN',sans-serif;font-size:14px;}

/* Login */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;}
.login-card{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:40px;width:360px;box-shadow:0 4px 20px rgba(0,0,0,.08);}
.login-card h1{font-size:20px;font-weight:700;margin-bottom:6px;}
.login-card p{color:var(--muted);font-size:13px;margin-bottom:24px;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em;}
.form-group input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;outline:none;}
.form-group input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.btn-login{width:100%;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;}
.btn-login:hover{background:#1d4ed8;}
.error-msg{background:#fff3f3;border:1px solid #f5c6cb;color:var(--danger);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;}

/* Admin layout */
.admin-header{background:var(--white);border-bottom:1px solid var(--border);padding:14px 24px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:100;}
.admin-header h1{font-size:16px;font-weight:700;flex:1;}
.admin-header .badge{background:#e8f0fe;color:var(--primary);border-radius:6px;padding:3px 10px;font-size:12px;font-weight:700;}
.btn-logout{padding:6px 14px;border:1px solid var(--border);border-radius:7px;background:transparent;cursor:pointer;font-size:12px;color:var(--muted);}
.btn-logout:hover{border-color:var(--danger);color:var(--danger);}
.admin-body{padding:24px;max-width:1100px;margin:0 auto;}

/* Stats bar */
.stats-row{display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;}
.stat-card{background:var(--white);border:1px solid var(--border);border-radius:10px;padding:16px 20px;flex:1;min-width:140px;}
.stat-card .stat-label{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;}
.stat-card .stat-value{font-size:26px;font-weight:800;margin-top:4px;}
.stat-card .stat-sub{font-size:12px;color:var(--muted);margin-top:2px;}

/* Table */
.table-wrap{background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden;}
.table-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.table-header h2{font-size:15px;font-weight:700;}
table{width:100%;border-collapse:collapse;}
th{background:#f8f9fa;padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border);}
td{padding:12px 14px;border-bottom:1px solid #f0f0f0;font-size:13px;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#f8f9ff;}
.score-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;}
.risk-良好{background:#d4edda;color:#155724;}
.risk-注意{background:#fff3cd;color:#856404;}
.risk-警戒{background:#f8d7da;color:#721c24;}
.risk-危険{background:#f5c6cb;color:#491217;}
.expand-btn{background:var(--primary);color:#fff;border:none;border-radius:6px;padding:5px 12px;cursor:pointer;font-size:12px;}
.expand-btn:hover{background:#1d4ed8;}

/* Detail panel */
.detail-row td{padding:0;}
.detail-panel{display:none;padding:20px;background:#fafbff;border-top:1px solid var(--border);}
.detail-panel.open{display:block;}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.detail-info-table{width:100%;border-collapse:collapse;font-size:13px;}
.detail-info-table th{background:#f0f4ff;padding:7px 10px;text-align:left;width:40%;font-weight:600;color:var(--muted);font-size:12px;}
.detail-info-table td{padding:7px 10px;border-bottom:1px solid var(--border);}
.answers-section{margin-top:16px;}
.answers-section h4{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;}
.cat-block{margin-bottom:12px;}
.cat-name{font-size:12px;font-weight:700;padding:5px 8px;background:#f0f4ff;border-radius:5px;margin-bottom:5px;}
.answer-row{display:flex;align-items:center;gap:8px;padding:4px 8px;border-radius:5px;font-size:12px;}
.answer-row:hover{background:#f5f7ff;}
.answer-row .qnum{color:var(--muted);font-weight:600;width:32px;flex-shrink:0;}
.answer-row .qtext{flex:1;}
.ans-badge{padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;flex-shrink:0;}
.ans-yes{background:#d4edda;color:#155724;}
.ans-unknown{background:#fff3cd;color:#856404;}
.ans-no{background:#f8d7da;color:#721c24;}

.error-box{background:#fff3f3;border:1px solid #f5c6cb;color:var(--danger);padding:14px 18px;border-radius:8px;margin-bottom:20px;}
.empty-state{text-align:center;padding:60px;color:var(--muted);}
.empty-state .icon{font-size:40px;margin-bottom:10px;}
</style>
</head>
<body>

<?php if (!$is_auth): ?>
<!-- ログイン画面 -->
<div class="login-wrap">
    <div class="login-card">
        <h1>🔐 管理画面</h1>
        <p>セキュリティ診断ツール 管理者専用</p>
        <?php if (!empty($login_error)): ?>
        <div class="error-msg"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>パスワード</label>
                <input type="password" name="password" autofocus required>
            </div>
            <button type="submit" class="btn-login">ログイン</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- 管理画面 -->
<header class="admin-header">
    <h1>📊 診断データ管理画面</h1>
    <span class="badge"><?= count($submissions) ?>件</span>
    <form method="POST" style="margin-left:8px">
        <button type="submit" name="logout" class="btn-logout">ログアウト</button>
    </form>
</header>

<div class="admin-body">

<?php if ($fetch_error): ?>
<div class="error-box">⚠️ <?= htmlspecialchars($fetch_error) ?></div>
<?php endif; ?>

<?php if (!empty($submissions)): ?>
<!-- 統計 -->
<?php
$avg_score  = count($submissions) ? round(array_sum(array_column($submissions,'display_score')) / count($submissions)) : 0;
$risk_counts = array_count_values(array_column($submissions, 'risk_level'));
?>
<div class="stats-row">
    <div class="stat-card"><div class="stat-label">総診断数</div><div class="stat-value"><?= count($submissions) ?></div><div class="stat-sub">累計</div></div>
    <div class="stat-card"><div class="stat-label">平均スコア</div><div class="stat-value"><?= $avg_score ?></div><div class="stat-sub">/ 100点</div></div>
    <div class="stat-card"><div class="stat-label">危険・警戒</div><div class="stat-value" style="color:#dc3545"><?= ($risk_counts['危険'] ?? 0) + ($risk_counts['警戒'] ?? 0) ?></div><div class="stat-sub">要フォロー</div></div>
    <div class="stat-card"><div class="stat-label">良好</div><div class="stat-value" style="color:#198754"><?= $risk_counts['良好'] ?? 0 ?></div><div class="stat-sub">対策済み</div></div>
</div>

<!-- 一覧テーブル -->
<div class="table-wrap">
    <div class="table-header"><h2>診断履歴</h2></div>
    <table>
        <thead>
            <tr>
                <th>診断日時</th><th>企業名</th><th>担当者</th><th>業種</th>
                <th>規模</th><th>スコア</th><th>リスク</th><th>想定被害(最小)</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($submissions as $i => $s):
            $dt = isset($s['created_at']) ? date('Y/m/d H:i', strtotime($s['created_at'])) : '−';
            $ind = $industry_labels[$s['industry'] ?? ''] ?? $s['industry'] ?? '−';
            $ec  = $ec_labels[$s['employee_count'] ?? ''] ?? $s['employee_count'] ?? '−';
            $score = $s['display_score'] ?? '−';
            $risk  = $s['risk_level'] ?? '−';
            $dmg   = isset($s['damage_min']) ? number_format($s['damage_min']) . '万円' : '−';
        ?>
        <tr>
            <td><?= $dt ?></td>
            <td><strong><?= htmlspecialchars($s['company_name'] ?? '−') ?></strong></td>
            <td><?= htmlspecialchars($s['contact_name'] ?? '−') ?></td>
            <td><?= htmlspecialchars($ind) ?></td>
            <td><?= htmlspecialchars($ec) ?></td>
            <td><strong><?= $score ?></strong>/100</td>
            <td><span class="score-pill risk-<?= $risk ?>"><?= $risk ?></span></td>
            <td><?= $dmg ?></td>
            <td><button class="expand-btn" onclick="toggleDetail(<?= $i ?>)">詳細</button></td>
        </tr>
        <tr class="detail-row">
            <td colspan="9">
                <div class="detail-panel" id="detail-<?= $i ?>">
                    <div class="detail-grid">
                        <!-- 企業情報 -->
                        <div>
                            <table class="detail-info-table">
                                <tr><th>企業名</th><td><?= htmlspecialchars($s['company_name'] ?? '') ?></td></tr>
                                <tr><th>担当者名</th><td><?= htmlspecialchars($s['contact_name'] ?? '−') ?></td></tr>
                                <tr><th>業種</th><td><?= htmlspecialchars($ind) ?></td></tr>
                                <tr><th>従業員数区分</th><td><?= htmlspecialchars($ec) ?></td></tr>
                                <tr><th>従業員数</th><td><?= intval($s['employees'] ?? 0) ?>名</td></tr>
                                <tr><th>PC・端末台数</th><td><?= intval($s['pc_count'] ?? 0) ?>台</td></tr>
                                <tr><th>個人情報取扱</th><td><?= ($s['has_personal_info'] ?? 0) ? 'あり' : 'なし' ?></td></tr>
                                <tr><th>総合スコア</th><td><strong><?= $score ?>/100</strong></td></tr>
                                <tr><th>リスクレベル</th><td><span class="score-pill risk-<?= $risk ?>"><?= $risk ?></span></td></tr>
                                <tr><th>想定被害(最小)</th><td><?= $dmg ?></td></tr>
                                <tr><th>想定被害(最大)</th><td><?= isset($s['damage_max']) ? number_format($s['damage_max']).'万円' : '−' ?></td></tr>
                            </table>
                        </div>
                        <!-- 回答 -->
                        <div class="answers-section">
                            <h4>回答一覧</h4>
                            <?php
                            $raw_answers = $s['answers'] ?? [];
                            if (is_string($raw_answers)) $raw_answers = json_decode($raw_answers, true) ?? [];
                            foreach ($categories_order as $ckey => [$icon, $cat_label, $qs]):
                            ?>
                            <div class="cat-block">
                                <div class="cat-name"><?= $icon ?> <?= $cat_label ?></div>
                                <?php foreach ($qs as $qk):
                                    $ans = $raw_answers[$qk] ?? 'no';
                                    $ans_lbl = $answer_labels[$ans] ?? $ans;
                                    $ans_class = $ans === 'yes' ? 'ans-yes' : ($ans === 'unknown' ? 'ans-unknown' : 'ans-no');
                                ?>
                                <div class="answer-row">
                                    <span class="qnum"><?= $qk ?></span>
                                    <span class="qtext"><?= htmlspecialchars($question_texts[$qk] ?? $qk) ?></span>
                                    <span class="ans-badge <?= $ans_class ?>"><?= $ans_lbl ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php else: ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="icon">📭</div>
        <p>まだ診断データがありません</p>
    </div>
</div>
<?php endif; ?>

</div>

<script>
function toggleDetail(i) {
    const panel = document.getElementById('detail-' + i);
    panel.classList.toggle('open');
}
</script>

<?php endif; ?>
</body>
</html>
