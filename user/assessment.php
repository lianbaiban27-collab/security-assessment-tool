<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 企業情報をセッションに保存
$_SESSION['company_name']    = htmlspecialchars($_POST['company_name'] ?? '');
$_SESSION['industry']        = htmlspecialchars($_POST['industry'] ?? '');
$_SESSION['employee_count']  = htmlspecialchars($_POST['employee_count'] ?? '');
$_SESSION['employees']       = intval($_POST['employees'] ?? 0);
$_SESSION['pc_count']        = intval($_POST['pc_count'] ?? 0);
$_SESSION['has_personal_info'] = intval($_POST['has_personal_info'] ?? 0);
$_SESSION['contact_name']    = htmlspecialchars($_POST['contact_name'] ?? '');

$company = $_SESSION['company_name'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>セキュリティチェックリスト | 企業セキュリティ診断ツール</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>企業セキュリティ診断ツール</h1>
            <p><?= $company ?> のセキュリティ診断</p>
        </header>

        <div class="progress-bar">
            <div class="step completed">1. 企業情報</div>
            <div class="step active">2. セキュリティ診断</div>
            <div class="step">3. 診断結果</div>
        </div>

        <div class="card">
            <h2>Step 2：セキュリティ状況のチェック</h2>
            <p class="subtitle">現在の対策状況について、各設問にお答えください（全26問）</p>

            <form action="result.php" method="POST">
                <!-- 企業情報を引き継ぐ -->
                <input type="hidden" name="company_name"     value="<?= $company ?>">
                <input type="hidden" name="industry"         value="<?= $_SESSION['industry'] ?>">
                <input type="hidden" name="employee_count"   value="<?= $_SESSION['employee_count'] ?>">
                <input type="hidden" name="employees"        value="<?= $_SESSION['employees'] ?>">
                <input type="hidden" name="pc_count"         value="<?= $_SESSION['pc_count'] ?>">
                <input type="hidden" name="has_personal_info" value="<?= $_SESSION['has_personal_info'] ?>">
                <input type="hidden" name="contact_name"     value="<?= $_SESSION['contact_name'] ?>">

                <?php
                // カテゴリ定義（新しい質問・カテゴリを追加）
                $categories = [
                    [
                        'icon'  => '🛡️',
                        'title' => 'ネットワーク・境界防御',
                        'points'=> 30,
                        'questions' => [
                            'Q01' => 'ファイアウォール（UTM）を導入し、定期的に更新していますか？',
                            'Q02' => '不正通信・C2サーバへの通信遮断対策を実施していますか？',
                            'Q03' => 'VPNを利用したリモートアクセス管理をしていますか？',
                            'Q04' => 'Wi-Fiは社員用と来客用でネットワークを分離していますか？',
                            'Q05' => 'ネットワークの通信ログを定期的に確認していますか？',
                            'Q06' => '社内ネットワークにアクセス可能な端末を制限していますか？',
                        ],
                    ],
                    [
                        'icon'  => '💻',
                        'title' => 'エンドポイント対策',
                        'points'=> 20,
                        'questions' => [
                            'Q07' => 'ウイルス対策ソフト（EDR含む）を全端末に導入していますか？',
                            'Q08' => 'OSやソフトウェアのセキュリティパッチを定期的に適用していますか？',
                            'Q09' => 'PC・モバイル端末の暗号化・リモートワイプ対策をしていますか？',
                            'Q10' => '私物デバイスの業務利用（BYOD）を制限・管理していますか？',
                        ],
                    ],
                    [
                        'icon'  => '💾',
                        'title' => 'データ管理・バックアップ',
                        'points'=> 25,
                        'questions' => [
                            'Q11' => '重要データのバックアップを日次以上の頻度で取得していますか？',
                            'Q12' => 'バックアップデータをオフサイト（別拠点・クラウド）に保存していますか？',
                            'Q13' => 'バックアップからの復旧テストを定期的に実施していますか？',
                            'Q14' => '重要ファイルへのアクセス権限を必要最小限に設定していますか？',
                            'Q15' => 'ファイルへのアクセスログを定期的に確認していますか？',
                        ],
                    ],
                    [
                        'icon'  => '🔑',
                        'title' => 'アクセス管理・認証',
                        'points'=> 25,
                        'questions' => [
                            'Q16' => '多要素認証（MFA）を主要システム・メールに導入していますか？',
                            'Q17' => '退職者・異動者のアカウント削除・権限変更を即日対応していますか？',
                            'Q18' => '特権アカウント（管理者権限）の使用を必要時のみに制限していますか？',
                            'Q19' => 'パスワードポリシー（複雑性・定期変更）を定めていますか？',
                            'Q20' => '不正ログインを検知できる仕組みを導入していますか？',
                        ],
                    ],
                    [
                        'icon'  => '📋',
                        'title' => 'インシデント対応・教育',
                        'points'=> 15,
                        'questions' => [
                            'Q21' => '従業員向けセキュリティ教育を年1回以上実施していますか？',
                            'Q22' => 'フィッシングメール訓練を実施したことがありますか？',
                            'Q23' => 'サイバー攻撃を受けた場合のインシデント対応手順を文書化していますか？',
                        ],
                    ],
                    [
                        'icon'  => '🏢',
                        'title' => '組織・ルール整備',
                        'points'=> 15,
                        'questions' => [
                            'Q24' => 'セキュリティに関する社内規程を整備していますか？',
                            'Q25' => 'セキュリティ対策の責任者を定めていますか？',
                            'Q26' => '社内のセキュリティ対策状況を定期的に評価していますか？',
                        ],
                    ],
                ];

                foreach ($categories as $cat): ?>
                <div class="check-category">
                    <h3><?= $cat['icon'] ?> <?= $cat['title'] ?>
                        <span class="cat-points">（配点 <?= $cat['points'] ?>点）</span>
                    </h3>
                    <?php foreach ($cat['questions'] as $qkey => $qtext): ?>
                    <div class="check-item">
                        <label><?= $qtext ?></label>
                        <div class="radio-group">
                            <label class="radio-yes"><input type="radio" name="answers[<?= $qkey ?>]" value="yes" required> はい（対策済み）</label>
                            <label class="radio-unknown"><input type="radio" name="answers[<?= $qkey ?>]" value="unknown"> わからない</label>
                            <label class="radio-no"><input type="radio" name="answers[<?= $qkey ?>]" value="no"> いいえ（未対策）</label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

                <div class="btn-wrap">
                    <a href="index.php" class="btn-secondary">← 戻る</a>
                    <button type="submit" class="btn-primary">診断結果を見る →</button>
                </div>
            </form>
        </div>

        <footer>
            <p>※ 本診断は業界統計データをもとにしたリスク試算です。実際の被害額は環境によって異なります。</p>
        </footer>
    </div>
</body>
</html>
