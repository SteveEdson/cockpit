<?php

$this->module("regions")->extend([

    'createRegion' => function($name, $data = []) {

        if (!trim($name)) {
            return false;
        }

        $configpath = $this->app->path('#storage:').'/regions';

        if (!$this->app->path('#storage:regions')) {

            if (!$this->app->helper('fs')->mkdir($configpath)) {
                return false;
            }
        }

        if ($this->exists($name)) {
            return false;
        }

        $time = time();

        $region = array_replace_recursive([
            'name'      => $name,
            'label'     => $name,
            '_id'       => uniqid($name),
            'fields'    => [],
            'template'  => '',
            'data'      => null,
            '_created'  => $time,
            '_modified' => $time
        ], $data);

        $export = var_export($region, true);

        if (!$this->app->helper('fs')->write("#storage:regions/{$name}.region.php", "<?php\n return {$export};")) {
            return false;
        }

        $this->app->trigger('regions.create', [$region]);

        return $region;
    },

    'updateRegion' => function($name, $data) {

        $metapath = $this->app->path("#storage:regions/{$name}.region.php");

        if (!$metapath) {
            return false;
        }

        $data['_modified'] = time();

        $region  = include($metapath);
        $region  = array_merge($region, $data);
        $export  = var_export($region, true);

        if (!$this->app->helper('fs')->write($metapath, "<?php\n return {$export};")) {
            return false;
        }

        $this->app->trigger('regions.update', [$region]);

        return $region;
    },

    'saveRegion' => function($name, $data) {

        if (!trim($name)) {
            return false;
        }

        return isset($data['_id']) ? $this->updateRegion($name, $data) : $this->createRegion($name, $data);
    },

    'removeRegion' => function($name) {

        if ($region = $this->region($name)) {

            $region = $regions["_id"];

            $this->app->helper("fs")->delete("#storage:regions/{$name}.region.php");
            $this->app->storage->dropregion("regions/{$region}");

            $this->app->trigger('regions.remove', [$name]);

            return true;
        }

        return false;
    },

    'regions' => function() {

        $regions = [];

        foreach($this->app->helper("fs")->ls('*.region.php', '#storage:regions') as $path) {

            $store = include($path->getPathName());
            $regions[$store['name']] = $store;
        }

        return $regions;
    },

    'exists' => function($name) {
        return $this->app->path("#storage:regions/{$name}.region.php");
    },

    'region' => function($name) {

        static $regions; // cache

        if (is_null($regions)) {
            $regions = [];
        }

        if (!is_string($name)) {
            return false;
        }

        if (!isset($regions[$name])) {

            $regions[$name] = false;

            if ($path = $this->exists($name)) {
                $regions[$name] = include($path);
            }
        }

        return $regions[$name];
    },

    'getRegionFieldValue' => function($region, $fieldname, $default = null) {

        $region = $this->region($region);

        return ($region && isset($region['data'][$fieldname])) ? $region['data'][$fieldname] : $default;
    },

    'render' => function($name, $params = []) {

        $region = $this->region($name);

        if (!$region) {
            return null;
        }

        $renderer = $this->app->renderer;

        $_fields  = isset($region['fields']) ? $region['fields'] : [];

        $fields = array_merge(isset($region['data']) && is_array($region['data']) ? $region['data']:[] , $params);

        $this->app->trigger('regions.render.before', [$name, &$region, $fields]);

        $output = $renderer->execute($region['template'], $fields);

        $this->app->trigger('regions.render.after', [$name, &$output]);

        return $output;
    }

]);

// extend app lexy parser
$app->renderer->extend(function($content){
    $content = preg_replace('/(\s*)@region\((.+?)\)/', '$1<?php echo cockpit("regions")->render($2); ?>', $content);
    return $content;
});

// REST
if (COCKPIT_REST) {

    $app->on('cockpit.rest.init', function($routes) {
        $routes['regions'] = 'Regions\\Controller\\RestApi';
    });
}

// ADMIN
if (COCKPIT_ADMIN && !COCKPIT_REST) {
    include_once(__DIR__.'/admin.php');
}
