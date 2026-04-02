<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ─── 企業情報 ───────────────────────────────────────────
$company_name    = htmlspecialchars($_POST['company_name'] ?? '');
$industry        = $_POST['industry'] ?? 'other';
$employee_count  = $_POST['employee_count'] ?? 'small';
$employees       = intval($_POST['employees'] ?? 10);
$pc_count        = intval($_POST['pc_count'] ?? 10);
$has_personal    = intval($_POST['has_personal_info'] ?? 0);
$contact_name    = htmlspecialchars($_POST['contact_name'] ?? '');
$answers         = $_POST['answers'] ?? [];

// ─── 業種・規模ラベル ────────────────────────────────────
$industry_labels = [
    'finance'      => '金融・保険',
    'medical'      => '医療・ヘルスケア',
    'manufacturing'=> '製造業',
    'it'           => 'IT・通信',
    'retail'       => '小売・EC',
    'construction' => '建設・不動産',
    'service'      => 'サービス業',
    'government'   => '官公庁・自治体',
    'education'    => '教育',
    'other'        => 'その他',
];
$ec_labels = [
    'small'      => '1〜29名',
    'medium'     => '30〜299名',
    'large'      => '300〜999名',
    'enterprise' => '1,000名以上',
];
$industry_label = $industry_labels[$industry] ?? 'その他';
$ec_label       = $ec_labels[$employee_count] ?? '';

// ─── カテゴリ・配点定義（26問・130点満点） ─────────────────
$categories = [
    'network' => [
        'icon'   => '🛡️',
        'label'  => 'ネットワーク・境界防御',
        'max'    => 30,
        'questions' => ['Q01','Q02','Q03','Q04','Q05','Q06'],
    ],
    'endpoint' => [
        'icon'   => '💻',
        'label'  => 'エンドポイント対策',
        'max'    => 20,
        'questions' => ['Q07','Q08','Q09','Q10'],
    ],
    'data' => [
        'icon'   => '💾',
        'label'  => 'データ管理・バックアップ',
        'max'    => 25,
        'questions' => ['Q11','Q12','Q13','Q14','Q15'],
    ],
    'access' => [
        'icon'   => '🔑',
        'label'  => 'アクセス管理・認証',
        'max'    => 25,
        'questions' => ['Q16','Q17','Q18','Q19','Q20'],
    ],
    'incident' => [
        'icon'   => '📋',
        'label'  => 'インシデント対応・教育',
        'max'    => 15,
        'questions' => ['Q21','Q22','Q23'],
    ],
    'governance' => [
        'icon'   => '🏢',
        'label'  => '組織・ルール整備',
        'max'    => 15,
        'questions' => ['Q24','Q25','Q26'],
    ],
];

// ─── スコア計算 ──────────────────────────────────────────
// yes=5点、unknown=2点、no=0点
$total_max   = 130;
$total_score = 0;
$cat_scores  = [];
$no_answers  = []; // 未対策の質問コード

foreach ($categories as $ckey => $cat) {
    $cat_max   = $cat['max'];
    $q_count   = count($cat['questions']);
    $pts_each  = $cat_max / $q_count;
    $cat_score = 0;
    foreach ($cat['questions'] as $qk) {
        $ans = $answers[$qk] ?? 'no';
        if ($ans === 'yes')     $cat_score += $pts_each;
        elseif ($ans === 'unknown') $cat_score += $pts_each * 0.4;
        else $no_answers[] = $qk;
    }
    $cat_score = round($cat_score);
    $cat_scores[$ckey] = $cat_score;
    $total_score += $cat_score;
}

// 表示スコア（100点満点に正規化）
$display_score = round(($total_score / $total_max) * 100);
$display_score = max(0, min(100, $display_score));

// ─── リスクレベル ────────────────────────────────────────
if ($display_score >= 80) {
    $risk_level   = '良好';
    $risk_color   = '#2e7d32';
    $risk_message = '基本的な対策は整っています';
} elseif ($display_score >= 60) {
    $risk_level   = '注意';
    $risk_color   = '#f57c00';
    $risk_message = '一部の対策が不足しています';
} elseif ($display_score >= 40) {
    $risk_level   = '警戒';
    $risk_color   = '#e53935';
    $risk_message = '重大なリスクが存在します';
} else {
    $risk_level   = '危険';
    $risk_color   = '#b71c1c';
    $risk_message = '早急な対策が必要です';
}

// ─── 業種別リスク係数 ────────────────────────────────────
$industry_risk = [
    'finance'      => 1.5,
    'medical'      => 1.4,
    'government'   => 1.3,
    'manufacturing'=> 1.2,
    'it'           => 1.1,
    'retail'       => 1.1,
    'education'    => 1.0,
    'construction' => 1.0,
    'service'      => 0.9,
    'other'        => 1.0,
];
$risk_factor = $industry_risk[$industry] ?? 1.0;

// スコア不足による乗数（低スコアほど被害拡大）
$score_multiplier = 1 + (1 - $display_score / 100) * 1.5;

// ─── 規模別基礎被害額（万円）───────────────────────────────
$base_damages = [
    'small'      => ['ransomware' => 200,  'leak' => 300,  'phishing' => 80,  'downtime' => 80 ],
    'medium'     => ['ransomware' => 800,  'leak' => 1200, 'phishing' => 300, 'downtime' => 300],
    'large'      => ['ransomware' => 2000, 'leak' => 4000, 'phishing' => 800, 'downtime' => 800],
    'enterprise' => ['ransomware' => 6000, 'leak' => 12000,'phishing' => 2000,'downtime' => 2000],
];
$base = $base_damages[$employee_count] ?? $base_damages['small'];

// 個人情報取り扱いで漏洩被害を増幅
if ($has_personal) $base['leak'] = round($base['leak'] * 1.5);

// 各シナリオの想定被害額
$scenario_labels = [
    'ransomware' => 'ランサムウェア（業務停止・復旧費用）',
    'leak'       => '情報漏洩（調査・通知・賠償費用）',
    'phishing'   => 'フィッシング・不正送金被害',
    'downtime'   => '業務停止・機会損失',
];
$scenario_risks = [];
foreach ($base as $k => $v) {
    $scenario_risks[$k] = round($v * $risk_factor * $score_multiplier);
}
$damage_total = array_sum($scenario_risks);
$damage_min   = round($damage_total * 0.3);
$damage_max   = round($damage_total * 1.7);

// ─── リスクドット色分け ──────────────────────────────────
function risk_dot_class($score, $max) {
    $pct = $score / $max;
    if ($pct >= 0.7) return 'risk-low';
    if ($pct >= 0.4) return 'risk-medium';
    return 'risk-high';
}

// ─── 改善推奨事項 ────────────────────────────────────────
$recommendations_map = [
    'Q01' => ['ファイアウォール（UTM）の導入',       'high',   'C2サーバとの通信を出口でブロックし、ランサムウェアの情報流出を防ぎます。'],
    'Q02' => ['不正通信遮断（C2ブロック）の実装',    'high',   'C2サーバリストを活用した自動遮断でランサムウェアの初期侵害を無効化します。'],
    'Q03' => ['VPNによるリモートアクセス制限',       'high',   'VPN未導入のリモートアクセスはブルートフォース攻撃の主要経路です。'],
    'Q04' => ['Wi-Fiネットワーク分離',               'medium', '来客・IoT機器を業務ネットワークから切り離し、横移動リスクを低減します。'],
    'Q05' => ['通信ログの定期確認',                  'medium', '異常通信の早期発見でインシデント対応時間を短縮できます。'],
    'Q06' => ['ネットワーク接続端末の制限（NAC）',   'high',   '未登録端末の接続を遮断し、内部不正・マルウェア持ち込みリスクを低減します。'],
    'Q07' => ['EDR/ウイルス対策の全端末導入',        'high',   'エンドポイントでの脅威検知・隔離は被害拡大防止の最重要対策です。'],
    'Q08' => ['セキュリティパッチの定期適用',        'high',   '未パッチの脆弱性は攻撃者の主要な侵入経路です。月次適用を推奨します。'],
    'Q09' => ['端末暗号化・リモートワイプ設定',      'medium', '端末紛失・盗難時のデータ流出リスクを最小化します。'],
    'Q10' => ['BYODポリシーの策定と管理',            'medium', '私物端末を通じたマルウェア侵入を防ぐためのルール整備が必要です。'],
    'Q11' => ['日次バックアップの実施',              'high',   'ランサムウェア被害後のデータ復旧に不可欠です。3-2-1ルールを推奨します。'],
    'Q12' => ['オフサイトへのバックアップ保存',      'high',   'オンプレのみのバックアップはランサムウェアで同時に暗号化されるリスクがあります。'],
    'Q13' => ['バックアップ復旧テストの実施',        'medium', '復旧テスト未実施のバックアップは本番では使えないケースが多数あります。'],
    'Q14' => ['ファイルアクセス権限の最小化',        'medium', '必要最小限の権限設定でランサムウェアの感染拡大を抑制します。'],
    'Q15' => ['ファイルアクセスログの定期確認',      'medium', 'アクセスログ監視で内部不正や不審アクセスを早期に検知できます。'],
    'Q16' => ['多要素認証（MFA）の導入',             'high',   'パスワード漏洩だけでは不正ログインできない環境を構築します。'],
    'Q17' => ['退職者アカウントの即時削除',          'high',   '退職者の認証情報が悪用されるリスクを排除します。手順の自動化も推奨します。'],
    'Q18' => ['特権アカウントの利用制限',            'medium', '管理者権限の乱用・悪用リスクを最小化します。必要時のみ昇格する運用を。'],
    'Q19' => ['パスワードポリシーの策定',            'medium', '脆弱なパスワードによるブルートフォース攻撃対策の基本です。'],
    'Q20' => ['不正ログイン検知の導入（SIEM/UEBA）', 'high',   '異常なログイン試行を自動検知し、アカウント乗っ取りを早期発見します。'],
    'Q21' => ['セキュリティ教育の定期実施',          'medium', '従業員のセキュリティ意識向上は最もコスパの高いリスク低減策です。'],
    'Q22' => ['フィッシング訓練の実施',              'medium', '実践的なフィッシング訓練で従業員の対応力を向上させます。'],
    'Q23' => ['インシデント対応手順の文書化',        'high',   '有事に迅速に動けるよう、対応フロー・連絡先を事前に整備してください。'],
    'Q24' => ['セキュリティ社内規程の整備',         'high',   '規程がなければ対策の基準が不明確になり、組織的な対応ができません。'],
    'Q25' => ['セキュリティ責任者の任命',            'high',   '責任者不在のままでは対策の推進・改善が機能しません。担当者を明確化してください。'],
    'Q26' => ['セキュリティ状況の定期評価',          'medium', '定期評価・見直しにより対策の形骸化を防ぎ、継続的改善を実現します。'],
];

$recs = [];
foreach ($no_answers as $qk) {
    if (isset($recommendations_map[$qk])) {
        [$title, $priority, $desc] = $recommendations_map[$qk];
        $recs[] = compact('title','priority','desc');
    }
}
// 優先度でソート
usort($recs, fn($a,$b) => ($a['priority']==='high'?0:($a['priority']==='medium'?1:2)) <=> ($b['priority']==='high'?0:($b['priority']==='medium'?1:2)));

// ─── DDHBOXプラン選定（5年リース・税別）────────────────────
$ddhbox_plans = [
    ['max_pc'=>10,   'plan'=>'プラン10',   'monthly'=>24300,   'yearly'=>291600  ],
    ['max_pc'=>20,   'plan'=>'プラン20',   'monthly'=>29800,   'yearly'=>357600  ],
    ['max_pc'=>30,   'plan'=>'プラン30',   'monthly'=>41300,   'yearly'=>495600  ],
    ['max_pc'=>50,   'plan'=>'プラン50',   'monthly'=>52900,   'yearly'=>634800  ],
    ['max_pc'=>100,  'plan'=>'プラン100',  'monthly'=>75900,   'yearly'=>910800  ],
    ['max_pc'=>300,  'plan'=>'プラン300',  'monthly'=>172300,  'yearly'=>2067600 ],
    ['max_pc'=>500,  'plan'=>'プラン500',  'monthly'=>282600,  'yearly'=>3391200 ],
    ['max_pc'=>1000, 'plan'=>'プラン1000', 'monthly'=>558100,  'yearly'=>6697200 ],
    ['max_pc'=>9999, 'plan'=>'エンタープライズ', 'monthly'=>0, 'yearly'=>0 ],
];
$selected_plan = end($ddhbox_plans);
foreach ($ddhbox_plans as $p) {
    if ($pc_count <= $p['max_pc']) { $selected_plan = $p; break; }
}

// urgency判定
$q_high_risk = ['Q01','Q02','Q03','Q07','Q08','Q11','Q12','Q16','Q20','Q23','Q24','Q25'];
$urgent_no   = array_intersect($q_high_risk, $no_answers);
if (count($urgent_no) >= 3)      { $urgency = 'urgent';    $urgency_label = '⚠️ 早急に検討'; }
elseif (count($urgent_no) >= 1)  { $urgency = 'recommend'; $urgency_label = '✅ 導入推奨'; }
else                              { $urgency = 'reference'; $urgency_label = '💡 参考提案'; }

// ─── necfruコスト試算 ────────────────────────────────────
$storage_gb       = max(100, $employees * 10);
$necfru_base      = 30000;
$necfru_storage   = round($storage_gb * 0.7);
$necfru_monthly   = $necfru_base + $necfru_storage;

// ─── ROI計算 ────────────────────────────────────────────
$ddhbox_year  = $selected_plan['yearly'] > 0 ? $selected_plan['yearly'] : $selected_plan['monthly'] * 12;
$necfru_year  = $necfru_monthly * 12;
$total_cost_y = round(($ddhbox_year + $necfru_year) / 10000);
$roi_x        = $damage_min > 0 ? round($damage_min / $total_cost_y, 1) : 0;

// ─── 業種別被害事例 ──────────────────────────────────────
$cases_by_industry = [
    'it' => [
        ['year'=>'2023', 'icon'=>'💻', 'title'=>'社労士向けシステム「社労夢」ランサムウェア被害',
         'org'=>'株式会社エムケイシステム（SaaS提供会社）', 'damage'=>'数十億円規模（推計）',
         'desc'=>'2023年6月、社労士向けクラウドシステム「社労夢」がランサムウェアに感染。2,754の社労士事務所が一斉にサービス停止。賞与・住民税変更などの繁忙期と重なり甚大な影響が出た。',
         'lesson'=>'SaaS事業者1社の被害が数千社に波及するリスク。自社のセキュリティが委託先・顧客の業務継続に直結する。',
         'comparison'=>'規模は異なりますが、同様の攻撃手法が貴社にも適用されるリスクがあります。',
         'source'=>'出典：各種報道・IPA 情報セキュリティ10大脅威2024'],
        ['year'=>'2024', 'icon'=>'📺', 'title'=>'ニコニコ動画 大規模サイバー攻撃',
         'org'=>'株式会社ドワンゴ', 'damage'=>'100億円超（推計）',
         'desc'=>'2024年6月、大規模なランサムウェア攻撃によりグループ全体のシステムが停止。KADOKAWAグループへの影響も含めると被害は100億円規模とされる。',
         'lesson'=>'データセンター規模の攻撃でも出口の不正通信遮断があれば初期の情報流出を防げる可能性があった。バックアップの完全性も重要。',
         'comparison'=>'規模は異なりますが、同様の攻撃手法が貴社にも適用されるリスクがあります。',
         'source'=>'出典：KADOKAWA グループ IR開示・各種報道（2024年）'],
    ],
    'medical' => [
        ['year'=>'2022', 'icon'=>'🏥', 'title'=>'大阪急性期・総合医療センター ランサムウェア被害',
         'org'=>'大阪急性期・総合医療センター', 'damage'=>'復旧費用 数億円・機会損失 数億円',
         'desc'=>'2022年10月、給食委託業者のVPNを踏み台にランサムウェアが侵入。電子カルテシステムが停止し、救急患者の受け入れ制限が約2カ月続いた。',
         'lesson'=>'取引先・委託業者経由のサプライチェーン攻撃。VPN機器の脆弱性管理とアクセス制御が重要。',
         'comparison'=>'医療機関は攻撃者にとって高優先度のターゲットです。',
         'source'=>'出典：大阪急性期・総合医療センター公表資料（2022年）'],
    ],
    'manufacturing' => [
        ['year'=>'2021', 'icon'=>'🏭', 'title'=>'自動車部品メーカー サプライチェーン攻撃',
         'org'=>'国内自動車部品メーカー（複数）', 'damage'=>'生産停止 数億円〜数十億円',
         'desc'=>'国内大手自動車メーカーの部品サプライヤーへのランサムウェア攻撃が相次ぎ、取引先の生産ライン停止にまで波及した。',
         'lesson'=>'中小製造業は大企業のサプライチェーン攻撃の起点として積極的に狙われる。取引先から対策証明を求められるケースも急増。',
         'comparison'=>'貴社が取引先の生産停止を引き起こすリスクがあります。',
         'source'=>'出典：IPA 情報セキュリティ10大脅威2022・各種報道'],
    ],
];

$cases = $cases_by_industry[$industry] ?? [
    ['year'=>'2024', 'icon'=>'🏢', 'title'=>'全国の中小企業へのサイバー攻撃統計',
     'org'=>'中小企業全般（警察庁統計）', 'damage'=>'中小企業の平均被害額：約1,500万円〜5,000万円',
     'desc'=>'2024年のサイバー攻撃は過去10年で最高となる6,862億パケット/日を記録。中小企業はセキュリティ対策が大企業より手薄なため、特に踏み台・サプライチェーン攻撃のターゲットになりやすい。',
     'lesson'=>'「うちは小さいから狙われない」は誤認。中小企業は大企業へのサプライチェーン攻撃の起点として積極的に狙われている。取引先から対策証明を求められるケースも急増。',
     'comparison'=>'貴社の想定被害額はこの事例と同規模です。同様の被害が起こりうる状況です。',
     'source'=>'出典：警察庁 サイバー攻撃被害報告・NICT NICTER観測レポート2024'],
];

// ─── レーダーチャートデータ ──────────────────────────────
$radar_labels = [];
$radar_scores = [];
$radar_maxes  = [];
foreach ($categories as $ckey => $cat) {
    $radar_labels[] = $cat['label'];
    $radar_scores[] = $cat_scores[$ckey];
    $radar_maxes[]  = $cat['max'];
}

// ─── 質問テキスト（PDF表示用） ────────────────────────────
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
$answer_labels = ['yes' => '◎ はい（対策済み）', 'unknown' => '△ わからない', 'no' => '✗ いいえ（未対策）'];

// ─── 日付 ────────────────────────────────────────────────
$today = date('Y年m月d日');

// ─── Supabase 保存 ───────────────────────────────────────
$supabase_url = $_ENV['SUPABASE_URL'] ?? $_SERVER['SUPABASE_URL'] ?? getenv('SUPABASE_URL');
$supabase_key = $_ENV['SUPABASE_ANON_KEY'] ?? $_SERVER['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY');
if ($supabase_url && $supabase_key) {
    $payload = json_encode([
        'company_name'     => $company_name,
        'contact_name'     => $contact_name,
        'industry'         => $industry,
        'employee_count'   => $employee_count,
        'employees'        => $employees,
        'pc_count'         => $pc_count,
        'has_personal_info'=> $has_personal,
        'answers'          => $answers,
        'total_score'      => $total_score,
        'display_score'    => $display_score,
        'risk_level'       => $risk_level,
        'damage_min'       => $damage_min,
        'damage_max'       => $damage_max,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($supabase_url . '/rest/v1/submissions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: ' . $supabase_key,
            'Authorization: Bearer ' . $supabase_key,
            'Prefer: return=minimal',
        ],
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>診断結果 | 企業セキュリティ診断ツール</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<div class="container">
    <header>
        <h1>企業セキュリティ診断ツール</h1>
        <p><?= $company_name ?> 様 診断結果レポート</p>
    </header>

    <div class="progress-bar">
        <div class="step completed">1. 企業情報</div>
        <div class="step completed">2. セキュリティ診断</div>
        <div class="step active">3. 診断結果</div>
    </div>

    <!-- ① 診断サマリー -->
    <div class="card result-summary">
        <div class="report-header">
            <div class="report-meta">
                <h2>セキュリティ診断レポート</h2>
                <p><?= $company_name ?> ／ <?= $industry_label ?> ／ <?= $ec_label ?></p>
                <p class="report-date">診断日：<?= $today ?></p>
                <p class="risk-message" style="color:<?= $risk_color ?>"><?= $risk_message ?></p>
            </div>
            <div class="score-circle" style="border-color:<?= $risk_color ?>; color:<?= $risk_color ?>">
                <span class="score-number"><?= $display_score ?></span>
                <span class="score-label">/ 100</span>
                <span class="risk-badge" style="background:<?= $risk_color ?>"><?= $risk_level ?></span>
            </div>
        </div>
    </div>

    <!-- ② カテゴリ別レーダーチャート -->
    <div class="card">
        <h2>カテゴリ別セキュリティスコア</h2>
        <div class="chart-wrap">
            <canvas id="radarChart" width="400" height="340"></canvas>
        </div>
        <div class="category-scores">
            <?php foreach ($categories as $ckey => $cat):
                $cs  = $cat_scores[$ckey];
                $cm  = $cat['max'];
                $pct = $cm > 0 ? round($cs/$cm*100) : 0;
                $bar_color = $pct >= 70 ? '#2e7d32' : ($pct >= 40 ? '#f57c00' : '#e53935');
            ?>
            <div class="cat-score-item">
                <span class="cat-icon"><?= $cat['icon'] ?></span>
                <div class="cat-bar-wrap">
                    <div class="cat-bar-label"><?= $cat['label'] ?></div>
                    <div class="cat-bar-track">
                        <div class="cat-bar-fill" style="width:<?= $pct ?>%; background:<?= $bar_color ?>"></div>
                    </div>
                </div>
                <span class="cat-score-num"><?= $cs ?>/<?= $cm ?>点</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ③ 想定被害額 -->
    <div class="card damage-card">
        <h2>想定される被害額レンジ</h2>
        <div class="damage-range">
            <div class="damage-range-item min">
                <span class="damage-range-label">最小想定</span>
                <span class="damage-range-value">約 <?= number_format($damage_min) ?> 万円</span>
            </div>
            <div class="damage-range-arrow">〜</div>
            <div class="damage-range-item max">
                <span class="damage-range-label">最大想定</span>
                <span class="damage-range-value">約 <?= number_format($damage_max) ?> 万円</span>
            </div>
        </div>

        <h3>被害シナリオ別内訳</h3>
        <div class="breakdown-list">
            <?php foreach ($scenario_risks as $sk => $sv):
                $dot_class = $sv > $base_damages[$employee_count][$sk] ? 'risk-high' : ($sv > $base_damages[$employee_count][$sk]*0.5 ? 'risk-medium' : 'risk-low');
            ?>
            <div class="breakdown-item">
                <div class="breakdown-label">
                    <span class="risk-dot <?= $dot_class ?>"></span>
                    <?= $scenario_labels[$sk] ?>
                </div>
                <div class="breakdown-amount">約 <?= number_format($sv) ?> 万円</div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="damage-note-small">※ IPA統計・Ponemon Institute調査・個人情報保護委員会ガイドラインをもとに算出した推計値です</p>
    </div>

    <!-- ④ DDHBOX提案 -->
    <div class="card product-card ddhbox-card urgency-<?= $urgency ?>">
        <div class="product-header">
            <div class="product-badge-wrap">
                <span class="urgency-badge <?= $urgency ?>"><?= $urgency_label ?></span>
            </div>
            <h2>DDHBOX ― 不正通信を出口で完全遮断</h2>
            <p class="product-sub">ネットワーク出口に繋ぐだけ。ランサムウェアのC2通信を全自動で遮断します</p>
        </div>

        <div class="plan-recommend">
            <div class="plan-box">
                <span class="plan-label">貴社推奨プラン（PC<?= $pc_count ?>台）</span>
                <span class="plan-name"><?= $selected_plan['plan'] ?></span>
                <?php if ($selected_plan['monthly'] > 0): ?>
                <div class="plan-price-row">
                    <div class="plan-price-item">
                        <span class="plan-price-label">月額（税別）</span>
                        <span class="plan-price-value">¥<?= number_format($selected_plan['monthly']) ?><small>/月</small></span>
                    </div>
                    <div class="plan-price-item">
                        <span class="plan-price-label">年額（税別）</span>
                        <span class="plan-price-value">¥<?= number_format($selected_plan['yearly']) ?><small>/年</small></span>
                    </div>
                    <div class="plan-price-item highlight">
                        <span class="plan-price-label">5年総額（税別）</span>
                        <span class="plan-price-value">¥<?= number_format($selected_plan['yearly']*5) ?></span>
                    </div>
                </div>
                <div class="plan-initial-cost">
                    <span class="plan-initial-label">初期費用（別途）</span>
                    <span class="plan-initial-value">本体機器 ¥282,400 ＋ 設置費用 ¥80,000（税別）</span>
                </div>
                <?php else: ?>
                <p style="color:#546e7a;font-size:0.9rem;margin-top:8px;">台数が多いためカスタムプランにてお見積もりします。お問い合わせください。</p>
                <div class="plan-initial-cost">
                    <span class="plan-initial-label">初期費用（別途）</span>
                    <span class="plan-initial-value">本体機器 ¥282,400 ＋ 設置費用 ¥80,000（税別）</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="product-features">
            <div class="feature-item">🔒 C2サーバリスト365日自動更新（ラック社JSOC）</div>
            <div class="feature-item">🏥 サイバー保険 <strong>年間300万円</strong> 自動付帯</div>
            <div class="feature-item">⚡ 工事不要・ネットワーク出口に繋ぐだけ</div>
            <div class="feature-item">📊 導入企業の <strong>約4社に1社（25.7%）</strong> で不正通信を検知</div>
            <div class="feature-item">🛟 インシデント後サポート（フォレンジック・データ復旧・弁護士相談）</div>
        </div>
    </div>

    <!-- ⑤ necfru MAM/DAM提案 -->
    <div class="card product-card necfru-card urgency-<?= $urgency ?>">
        <div class="product-header">
            <div class="product-badge-wrap">
                <span class="urgency-badge <?= $urgency ?>"><?= $urgency_label ?></span>
            </div>
            <h2>necfru MAM/DAM ― クラウドでデータを守る・探せる・続ける</h2>
            <p class="product-sub">消せないデータを低コストで長期保管。ランサムウェア被害後もデータを復元できます</p>
        </div>

        <div class="plan-recommend">
            <div class="plan-box necfru-box">
                <span class="plan-label">貴社費用試算（従業員<?= $employees ?>名・約<?= $storage_gb ?>GB）</span>
                <div class="plan-price-row">
                    <div class="plan-price-item">
                        <span class="plan-price-label">基本料（ユーザー無制限）</span>
                        <span class="plan-price-value">¥<?= number_format($necfru_base) ?><small>/月</small></span>
                    </div>
                    <div class="plan-price-item">
                        <span class="plan-price-label">ストレージ概算</span>
                        <span class="plan-price-value">¥<?= number_format($necfru_storage) ?><small>/月</small></span>
                    </div>
                    <div class="plan-price-item highlight">
                        <span class="plan-price-label">月額合計（概算）</span>
                        <span class="plan-price-value">¥<?= number_format($necfru_monthly) ?><small>/月</small></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="product-features">
            <div class="feature-item">☁️ クラウド分散保管・90日ゴミ箱でデータ消失から復旧</div>
            <div class="feature-item">🔍 タグ・メタデータで瞬時に検索・管理</div>
            <div class="feature-item">🔐 権限管理・操作ログで内部不正リスクを低減</div>
            <div class="feature-item">🔗 外部共有URLでUSB/メール誤送信リスクを排除</div>
            <div class="feature-item">📦 HOT/COLDストレージでコスト最適化（HOT 1.2円/GB）</div>
        </div>
    </div>

    <!-- ⑥ 費用対効果サマリー -->
    <div class="card roi-card">
        <h2>費用対効果サマリー</h2>
        <div class="roi-grid">
            <div class="roi-item risk">
                <span class="roi-label">想定被害額（最小）</span>
                <span class="roi-value">約 <?= number_format($damage_min) ?> 万円</span>
            </div>
            <div class="roi-vs">vs</div>
            <div class="roi-item cost">
                <span class="roi-label">年間導入費用（概算）</span>
                <span class="roi-value">約 <?= $total_cost_y ?> 万円</span>
                <span class="roi-detail">DDHBOX <?= round($ddhbox_year/10000) ?>万円 + necfru <?= round($necfru_year/10000) ?>万円</span>
            </div>
        </div>
        <?php if ($roi_x > 0): ?>
        <div class="roi-message">
            想定被害額（最小）は年間導入費用の <strong><?= $roi_x ?>倍</strong> です。<br>
            月額 <?= round($total_cost_y/12) ?> 万円の投資で、<?= number_format($damage_min) ?> 万円以上のリスクに備えることができます。
        </div>
        <?php endif; ?>
    </div>

    <!-- ⑦ 改善推奨事項 -->
    <?php if (!empty($recs)): ?>
    <div class="card">
        <h2>改善推奨事項</h2>
        <div class="rec-list">
            <?php foreach (array_slice($recs, 0, 8) as $rec): ?>
            <div class="rec-item priority-<?= $rec['priority'] ?>">
                <span class="priority-badge"><?= $rec['priority'] === 'high' ? '⚠️ 優先対応' : '📌 推奨' ?></span>
                <strong><?= $rec['title'] ?></strong>
                <p><?= $rec['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ⑧ 実際の被害事例 -->
    <div class="card cases-card">
        <h2>実際の被害事例：同業種・類似企業での事件</h2>
        <p class="subtitle">貴社と類似した業種・規模・セキュリティ状況の企業で実際に起きた事例です</p>
        <div class="cases-list">
            <?php foreach ($cases as $case): ?>
            <div class="case-item">
                <div class="case-header">
                    <span class="case-icon"><?= $case['icon'] ?></span>
                    <div class="case-title-wrap">
                        <span class="case-year"><?= $case['year'] ?>年</span>
                        <h3 class="case-title"><?= $case['title'] ?></h3>
                        <span class="case-org"><?= $case['org'] ?></span>
                    </div>
                    <div class="case-damage-wrap">
                        <span class="case-damage-label">実際の被害額</span>
                        <span class="case-damage-amount"><?= $case['damage'] ?></span>
                    </div>
                </div>
                <p class="case-description"><?= $case['desc'] ?></p>
                <div class="case-footer">
                    <div class="case-lesson">
                        <span class="lesson-label">教訓</span>
                        <?= $case['lesson'] ?>
                    </div>
                    <div class="case-comparison">
                        <span class="comparison-icon">⚠️</span>
                        <?= $case['comparison'] ?>
                    </div>
                </div>
                <p class="case-source"><?= $case['source'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="cases-summary">
            <p>上記事例の共通点：<strong>不正通信の「出口対策」が未整備</strong>だったため、侵入後の情報流出・被害拡大を防げませんでした。</p>
        </div>
    </div>

    <!-- ⑨ アクション -->
    <div class="card cta-card">
        <h2>次のステップ</h2>
        <p>本診断結果をもとに、DDHBOXとnecfru MAM/DAMの導入をご検討ください。</p>
        <div class="cta-actions">
            <button onclick="window.print()" class="btn-primary">レポートを印刷・PDF保存</button>
            <a href="index.php" class="btn-secondary">別の企業を診断する</a>
        </div>
    </div>

    <!-- ⑩ 印刷専用：企業情報・回答一覧（画面では非表示） -->
    <div class="print-only-section">
        <h2 class="print-section-title">■ 入力情報・回答一覧</h2>

        <table class="print-info-table">
            <tr><th>企業名</th><td><?= $company_name ?></td><th>担当者名</th><td><?= $contact_name ?: '−' ?></td></tr>
            <tr><th>業種</th><td><?= $industry_label ?></td><th>従業員数区分</th><td><?= $ec_label ?></td></tr>
            <tr><th>従業員数</th><td><?= $employees ?>名</td><th>PC・端末台数</th><td><?= $pc_count ?>台</td></tr>
            <tr><th>個人情報取扱</th><td><?= $has_personal ? 'あり' : 'なし' ?></td><th>診断日</th><td><?= $today ?></td></tr>
        </table>

        <?php foreach ($categories as $ckey => $cat): ?>
        <div class="print-category">
            <div class="print-cat-header"><?= $cat['icon'] ?> <?= $cat['label'] ?>
                （<?= $cat_scores[$ckey] ?>/<?= $cat['max'] ?>点）
            </div>
            <table class="print-answer-table">
                <thead><tr><th>No.</th><th>質問</th><th>回答</th></tr></thead>
                <tbody>
                <?php foreach ($cat['questions'] as $qk):
                    $ans = $answers[$qk] ?? 'no';
                    $ans_label = $answer_labels[$ans] ?? $ans;
                    $ans_class = $ans === 'yes' ? 'ans-yes' : ($ans === 'unknown' ? 'ans-unknown' : 'ans-no');
                ?>
                <tr>
                    <td class="print-qnum"><?= $qk ?></td>
                    <td class="print-qtext"><?= $question_texts[$qk] ?? '' ?></td>
                    <td class="print-ans <?= $ans_class ?>"><?= $ans_label ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <footer>
        <p>※ 本診断は業界統計データをもとにしたリスク試算です。実際の被害額は環境によって異なります。</p>
    </footer>
</div>

<script>
// レーダーチャート描画
const ctx = document.getElementById('radarChart').getContext('2d');
new Chart(ctx, {
    type: 'radar',
    data: {
        labels: <?= json_encode($radar_labels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: '現状スコア',
            data: <?= json_encode($radar_scores) ?>,
            backgroundColor: 'rgba(57, 73, 171, 0.2)',
            borderColor: 'rgba(57, 73, 171, 0.9)',
            borderWidth: 2,
            pointBackgroundColor: 'rgba(57, 73, 171, 1)',
            pointRadius: 4,
        }, {
            label: '満点',
            data: <?= json_encode($radar_maxes) ?>,
            backgroundColor: 'rgba(200, 200, 200, 0.1)',
            borderColor: 'rgba(200, 200, 200, 0.5)',
            borderWidth: 1,
            borderDash: [4, 4],
            pointRadius: 0,
        }]
    },
    options: {
        responsive: true,
        scales: {
            r: {
                beginAtZero: true,
                ticks: { display: false },
                grid: { color: 'rgba(0,0,0,0.08)' },
                pointLabels: { font: { size: 11 } }
            }
        },
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 12 } } }
        }
    }
});

// カテゴリバーのアニメーション
document.querySelectorAll('.cat-bar-fill').forEach(bar => {
    const w = bar.style.width;
    bar.style.width = '0';
    setTimeout(() => { bar.style.transition = 'width 0.8s ease'; bar.style.width = w; }, 100);
});
</script>
</body>
</html>
