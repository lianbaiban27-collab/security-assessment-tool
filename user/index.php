<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企業セキュリティ診断ツール</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>企業セキュリティ診断ツール</h1>
            <p>貴社のセキュリティリスクを診断し、想定被害額と対策プランをご提示します</p>
        </header>

        <div class="card">
            <h2>Step 1：企業情報の入力</h2>
            <form action="assessment.php" method="POST">

                <div class="form-group">
                    <label for="company_name">企業名 <span class="required">*</span></label>
                    <input type="text" id="company_name" name="company_name" required placeholder="例：株式会社〇〇">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="industry">業種 <span class="required">*</span></label>
                        <select id="industry" name="industry" required>
                            <option value="">選択してください</option>
                            <option value="finance">金融・保険</option>
                            <option value="medical">医療・ヘルスケア</option>
                            <option value="manufacturing">製造業</option>
                            <option value="it">IT・通信</option>
                            <option value="retail">小売・EC</option>
                            <option value="construction">建設・不動産</option>
                            <option value="service">サービス業</option>
                            <option value="government">官公庁・自治体</option>
                            <option value="education">教育</option>
                            <option value="other">その他</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="employee_count">従業員数区分 <span class="required">*</span></label>
                        <select id="employee_count" name="employee_count" required>
                            <option value="">選択してください</option>
                            <option value="small">1〜29名</option>
                            <option value="medium">30〜299名</option>
                            <option value="large">300〜999名</option>
                            <option value="enterprise">1,000名以上</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="employees">従業員数（人数） <span class="required">*</span></label>
                        <input type="number" id="employees" name="employees" min="1" max="99999" required placeholder="例：50">
                    </div>
                    <div class="form-group">
                        <label for="pc_count">PC・端末台数 <span class="required">*</span></label>
                        <input type="number" id="pc_count" name="pc_count" min="1" max="99999" required placeholder="例：30">
                        <span class="field-note">DDHBOXのプラン選定に使用します</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>個人情報の取り扱い <span class="required">*</span></label>
                    <div class="radio-group">
                        <label><input type="radio" name="has_personal_info" value="1" required> 取り扱っている（顧客・従業員情報等）</label>
                        <label><input type="radio" name="has_personal_info" value="0"> 取り扱っていない</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="contact_name">担当者名（任意）</label>
                    <input type="text" id="contact_name" name="contact_name" placeholder="例：山田 太郎">
                </div>

                <div class="btn-wrap">
                    <button type="submit" class="btn-primary">セキュリティ診断を開始する →</button>
                </div>
            </form>
        </div>

        <footer>
            <p>※ 本診断は業界統計データをもとにしたリスク試算です。実際の被害額は環境によって異なります。</p>
        </footer>
    </div>
</body>
</html>
