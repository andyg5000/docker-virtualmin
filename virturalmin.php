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
    shell_exec('docker rm -v ' . $container);
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
        $cmd .= ' -m 256m -c 75';
      break;
      case 'Wordpress':
        $cmd .= ' -v ' . $home . '/public_html:/usr/share/nginx/www:ro ';
        $cmd .= ' -v ' . $home . '/public_html/wp/wp-content:/usr/share/nginx/www/wp/wp-content:rw ';
        $cmd .= ' -v ' . $home . '/public_html/wp-content:/usr/share/nginx/www/wp-content:rw ';
        $cmd .= ' -m 128m -c 75';
      break;
      default:
        $cmd .= ' -v ' . $home . '/public_html:/usr/share/nginx/www ';
    }
    $cmd .= ' -v /home/config/ssmtp/ssmtp.conf:/etc/ssmtp/ssmtp.conf:ro ';
    $cmd .= ' -v ' . $home . '/logs:/var/log/nginx ';
    $cmd .= ' -v ' . $home . '/etc/nginx.conf:/etc/nginx/sites-enabled/nginx.conf ';
    $cmd .= ' -v /home/config/php/ssmtp.ini:/etc/php5/fpm/conf.d/ssmtp.ini:ro ';
    $cmd .= ' -v ' . $home . '/etc/php5/php.ini:/etc/php5/fpm/conf.d/zzz_local.ini ';
    $cmd .= ' -e VIRTUAL_HOST=www.' . $site_name . ',' . $site_name . ' ';
    $cmd .= ' -e DATABASE_HOST=10.132.129.239 ';
    $cmd .= ' --name ' . $site_name . ' ';
    $cmd .= ' --restart=always ';
    $cmd .= ' andyg5000/nginx';
    $docker = shell_exec($cmd);
  }
  $docker = shell_exec('docker run -d --restart=always -m 128m -c 128 -p 80:80 -p 443:443 --name nginx -v /var/log/nginx:/var/log/nginx -v /tmp/cache:/var/nginx -v /home/config/nginx/nginx.conf:/etc/nginx/nginx.conf -v /home/config/ssl:/etc/nginx/ssl -v /tmp/nginx:/etc/nginx/conf.d -t nginx');
  $docker = shell_exec('docker run -d --restart=always -m 128m -c 128 --name nginx-gen --volumes-from nginx  -v /var/run/docker.sock:/tmp/docker.sock -v /home/config/templates:/etc/docker-gen/templates -t jwilder/docker-gen:0.3.4 -notify-sighup nginx -watch --only-published /etc/docker-gen/templates/nginx.tmpl /etc/nginx/conf.d/default.conf -tlskey=/home/config/ssh/server.key -tslcert=/home/config/ssh/server.crt --tlsverify=false');
}
