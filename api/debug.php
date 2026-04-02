<?php
// 一時デバッグ用 - 確認後削除
echo "getenv: " . (getenv('SUPABASE_URL') ? 'OK' : 'NG') . "\n";
echo "_ENV: " . (isset($_ENV['SUPABASE_URL']) ? 'OK' : 'NG') . "\n";
echo "_SERVER: " . (isset($_SERVER['SUPABASE_URL']) ? 'OK' : 'NG') . "\n";
echo "curl enabled: " . (function_exists('curl_init') ? 'YES' : 'NO') . "\n";
