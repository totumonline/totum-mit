<?php
$host = 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/';
$settings['h_og_title'] = $settings['h_og_title'] ?? 'Totum';
if (empty($settings['h_title'])) {
    $settings['h_title'] = 'TOTUM';
}
$settings['h_og_description'] = $settings['h_og_description'] ?? '';
$settings['h_og_image'] = empty($settings['h_og_image'][0]['file']) ? 'imgs/hand.png' : 'fls/' . $settings['h_og_image'][0]['file'];
?>
<title><?= (empty($title) ? '' : $title . ' — ') . $settings['h_title'] ?></title>
<meta property="og:image" content="<?= $host . $settings['h_og_image'] ?>"/>
<meta property="og:url" content="<?= $host ?>"/>
<meta property="og:title" content="<?= ($settings['h_og_title']) ?>"/>
<meta property="og:description"
      content="<?= ($settings['h_og_description']) ?>"/>