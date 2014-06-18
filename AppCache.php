<?php

namespace mdm\clienttools;

use \Yii;
use yii\web\View;
use yii\helpers\FileHelper;

/**
 * Description of AppCache
 *
 * @author MDMunir
 */
class AppCache extends \yii\base\ActionFilter
{
    public $extraCaches = [];
    public $actions = [];
    private $_manifest_file;
    public $rel = true;

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        $view = $action->controller->view;
        $js = <<<JS
if (window.applicationCache) {
	window.applicationCache.addEventListener('updateready', function(e) {
		if (window.applicationCache.status == window.applicationCache.UPDATEREADY) {
			window.applicationCache.swapCache();
			//window.location.reload();
		}
	}, false);
}
JS;
        $view->registerJs($js, View::POS_BEGIN);
        $this->_manifest_file = static::getFileName($action->uniqueId, true, $this->rel);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        $this->createManifest($action->uniqueId, $result);
        return $result;
    }

    protected function createManifest($id, $html)
    {
        try {
            $filename = $this->getFileName($id);
            if (@file_get_contents($filename) == false) {
                $caches = [];
                $paths = [];
                $baseUrl = Yii::getAlias('@web') . '/';
                $basePath = Yii::getAlias('@webroot') . '/';

                // css
                $matches = [];
                $pattern = '/<link [^>]*href="?([^">]+)"?/';
                preg_match_all($pattern, $html, $matches);
                if (isset($matches[1])) {
                    foreach ($matches[1] as $href) {
                        $caches[$href] = true;
                        if (($path = $this->convertUrlToPath($href, $basePath, $baseUrl)) !== false) {
                            $path = dirname($path);
                            if (!isset($paths[$path]) && is_dir($path)) {
                                $paths[$path] = true;
                                foreach (FileHelper::findFiles($path) as $file) {
                                    $caches[$this->convertPathToUrl($file, $basePath, $baseUrl)] = true;
                                }
                            }
                        }
                    }
                }

                // js
                $matches = [];
                $pattern = '/<script [^>]*src="?([^">]+)"?/';
                preg_match_all($pattern, $html, $matches);
                if (isset($matches[1])) {
                    foreach ($matches[1] as $src) {
                        $caches[$src] = true;
                    }
                }

                // img
                $matches = [];
                $pattern = '/<img [^>]*src="?([^">]+)"?/';
                preg_match_all($pattern, $html, $matches);
                if (isset($matches[1])) {
                    foreach ($matches[1] as $src) {
                        if (strpos($src, 'data:') !== 0) {
                            $caches[$src] = true;
                        }
                    }
                }
                unset($caches[false]);

                $data = array_keys($caches);
                if ($this->rel) {
                    $l = strlen($baseUrl);
                    foreach ($data as $key => $url) {
                        if (strpos($url, $baseUrl) === 0) {
                            $data[$key] = substr($url, $l);
                        }
                    }
                }
                $view = new View();
                $manifest = $view->renderPhpFile(Yii::getAlias('@mdm/clienttools/manifest.php'), [
                    'caches' => array_merge($data, $this->extraCaches)
                ]);
                FileHelper::createDirectory(dirname($filename));
                file_put_contents($filename, $manifest);
            }
        } catch (\Exception $exc) {
            \Yii::error($exc->getMessage());
        }
    }

    private function convertPathToUrl($path, $basePath, $baseUrl)
    {
        if ($baseUrl && $basePath && strpos($path, $basePath) === 0) {
            return $baseUrl . substr($path, strlen($basePath));
        }
        return false;
    }

    private function convertUrlToPath($url, $basePath, $baseUrl)
    {
        if ($baseUrl && $basePath && strpos($url, $baseUrl) === 0) {
            return $basePath . substr($url, strlen($baseUrl));
        }
        return false;
    }

    private static function getFileName($id, $url = false, $rel = true)
    {
        $key = sprintf('%x', crc32($id . __CLASS__));
        if ($url) {
            return ($rel ? '' : Yii::getAlias('@web') . '/') . "{$key}.manifest";
        } else {
            return Yii::getAlias("@webroot/{$key}.manifest");
        }
    }

    public static function invalidate($id)
    {
        $filename = static::getFileName($id);
        if (($content = @file_get_contents($filename)) !== false) {
            $lines = explode("\n", $content);
            $lines[1] = '#' . time();
            file_put_contents($filename, implode("\n", $lines));
        }
    }

    public function getManifestFile()
    {
        return $this->_manifest_file;
    }

    protected function isActive($action)
    {
        return in_array($action->id, $this->actions, true);
    }
}