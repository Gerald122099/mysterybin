<?php
// get_config.php
header('Content-Type: text/plain; charset=UTF-8');

$configPath = __DIR__ . '/config.json';
$defaults = [
  "weight_thresh_g" => 50,
  "water_threshold" => 1800,
  "reward_on_ms"    => 2000,
  "a_min" => 100, "a_max" => 150,
  "b_min" => 151, "b_max" => 200,
  "config_version" => 1,
  "updated_at" => date('c'),
];

$data = $defaults;
if (file_exists($configPath)) {
  $raw = @file_get_contents($configPath);
  $json = json_decode($raw, true);
  if (is_array($json)) $data = array_merge($defaults, $json);
}

// Build query-string style output (no URL-encoding needed for numbers)
$out = [
  "weight_thresh_g={$data['weight_thresh_g']}",
  "water_threshold={$data['water_threshold']}",
  "reward_on_ms={$data['reward_on_ms']}",
  "a_min={$data['a_min']}",
  "a_max={$data['a_max']}",
  "b_min={$data['b_min']}",
  "b_max={$data['b_max']}",
  "ver={$data['config_version']}",
  "updated_at=" . rawurlencode($data['updated_at']), // safe
];

echo implode('&', $out);
