<?php
$url = $_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL');
$key = $_ENV['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY');

echo "URL: " . ($url ? substr($url,0,30).'...' : 'EMPTY') . "\n";
echo "KEY: " . ($key ? substr($key,0,20).'...' : 'EMPTY') . "\n";

$payload = json_encode([
    'company_name'   => 'PHPテスト',
    'industry'       => 'it',
    'employee_count' => 'small',
    'employees'      => 10,
    'pc_count'       => 5,
    'has_personal_info' => 1,
    'answers'        => ['Q01'=>'yes','Q02'=>'no'],
    'total_score'    => 5,
    'display_score'  => 50,
    'risk_level'     => '注意',
    'damage_min'     => 100,
    'damage_max'     => 300,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($url . '/rest/v1/submissions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Prefer: return=minimal',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "HTTP: $code\n";
echo "Response: $resp\n";
echo "cURL error: $err\n";
