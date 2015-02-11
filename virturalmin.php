<?php

// $docker = shell_exec('docker kill \$(docker ps -aq)');
// $docker = shell_exec('docker rm \$(docker ps -aq)');
$output = shell_exec('virtualmin list-domains --simple-multiline');
$output = explode("\n", $output);
$sites = array();
foreach ($output as $line) {
  if (!preg_match('/^[\s]+/', $line) && !empty($line)) {
    $site = $line;
    $sites[$line] = array();
    print $line . "\n";
  }
  else {
    $values = explode(':', $line);
    if (!empty($values[1])) {
      $sites[$site][trim($values[0])] = trim($values[1]);
    }
  }
}
// Start each container.
foreach ($sites as $site_name => $site) {
  $home = $site['Home directory'];
  $cmd = 'docker run -d -p 80';
  $cmd .= ' -v ' . $home . '/public_html:/usr/share/nginx/www ';
  $cmd .= ' -v ' . $home . '/logs:/var/log/nginx ';
  $cmd .= ' -v ' . $home . '/etc/nginx.conf:/etc/nginx/sites-enabled/nginx.conf ';
  $cmd .= ' -e VIRTUAL_HOST=www.' . $site_name . ',' . $site_name . ' ';
  $cmd .= ' --name ' . $site_name . ' ';
  $cmd .= ' andyg5000/nginx';
  $docker = shell_exec($cmd);
}

$docker = shell_exec('docker run -d -p 80:80 --name proxy -v /var/run/docker.sock:/tmp/docker.sock jwilder/nginx-proxy');
