<?php

// Update all sites config.
virtualmin_sites_update_config();

// Kill and remove existing containers.
docker_kill_containers();
docker_rm_containers();

// Start all containers.
docker_start_containers();

function docker_kill_containers() {
  foreach (docker_containers() as $container) {
    shell_exec('docker kill ' . $container);
  }
}

function docker_rm_containers() {
  foreach (docker_containers() as $container) {
    shell_exec('docker rm ' . $container);
  }
}

function docker_containers() {
  $containers = shell_exec('docker ps -aq');
  $containers = explode("\n", $containers);
  return (array) $containers;
}

function virtualmin_sites_update_config() {
  foreach (virtualmin_sites() as $domain => $site) {
    $template_dir = strtolower($site['Template']);
    $cmd = 'sed \'s/${DOM}/' . $domain . '/g\' "/etc/skel/' . $template_dir . '/etc/nginx.conf" > "' .  $site['Home directory'] . '/etc/nginx.conf"';
    shell_exec($cmd);
  }
}

function virtualmin_sites() {
  $output = shell_exec('virtualmin list-domains --simple-multiline');
  $output = explode("\n", $output);
  $sites = array();
  foreach ($output as $line) {
    if (!preg_match('/^[\s]+/', $line) && !empty($line)) {
      $site = $line;
      $sites[$line] = array();
    }
    else {
      $values = explode(':', $line);
      if (!empty($values[1])) {
        $sites[$site][trim($values[0])] = trim($values[1]);
      }
    }
  }
  return $sites;
}


function docker_start_containers() {
  // Start each container.
  foreach (virtualmin_sites() as $site_name => $site) {
    $home = $site['Home directory'];
    $cmd = 'docker run -d -p 80';
    switch ($site['Template']) {
      case 'Drupal':
        $cmd .= ' -v ' . $home . '/public_html:/usr/share/nginx/www:ro ';
        $cmd .= ' -v ' . $home . '/public_html/sites/default/files:/usr/share/nginx/www/sites/default/files:rw ';
      break;
      case 'Wordpress':
        $cmd .= ' -v ' . $home . '/public_html:/usr/share/nginx/www:ro ';
        $cmd .= ' -v ' . $home . '/public_html/wp/wp-content:/usr/share/nginx/www/wp/wp-content:rw ';
      break;
      default:
        $cmd .= ' -v ' . $home . '/public_html:/usr/share/nginx/www ';    
    }
    $cmd .= ' -v ' . $home . '/logs:/var/log/nginx ';
    $cmd .= ' -v ' . $home . '/etc/nginx.conf:/etc/nginx/sites-enabled/nginx.conf ';
    $cmd .= ' -e VIRTUAL_HOST=www.' . $site_name . ',' . $site_name . ' ';
    $cmd .= ' --name ' . $site_name . ' ';
    $cmd .= ' andyg5000/nginx';
    $docker = shell_exec($cmd);
  }

  $docker = shell_exec('docker run -d -p 80:80 --name proxy -v /var/run/docker.sock:/tmp/docker.sock jwilder/nginx-proxy');
}
