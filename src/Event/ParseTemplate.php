<?php
namespace Think\Event;

use Think\Storage;
use Think\Template;

class ParseTemplate implements EventInterface
{

    /**
     * 执行入口
     *
     * @param $data
     * @return mixed|void
     * @throws \Think\Exception
     */
    public function run(&$data)
    {
        if (!empty($data['content'])) {
            if ($this->checkContentCache($data['content'], $data['prefix'])) {
                $tmplCacheFile = C('TMPL_CACHE_PATH') . md5($data['content']) . C('TMPL_CACHE_SUFFIX');
                Storage::load($tmplCacheFile, $data['var']);
            } else {
                $tpl = new Template();
                $tpl->fetch($data['content'], $data['var']);
            }
        } else {
            if ($this->checkCache($data['file'])) {
                $tmplCacheFile = C('TMPL_CACHE_PATH') . md5($data['file']) . C('TMPL_CACHE_SUFFIX');
                Storage::load($tmplCacheFile, $data['var']);
            } else {
                $tpl = new Template();
                $tpl->fetch($data['file'], $data['var']);
            }
        }
    }

    /**
     * 检查缓存文件是否有效
     *
     * @param $tmplTemplateFile
     * @return bool
     */
    protected function checkCache($tmplTemplateFile)
    {
        if (C('TMPL_CACHE_EXPIRE') <= 0) {
            return false;
        }
        $tmplCacheFile = C('TMPL_CACHE_PATH') . md5($tmplTemplateFile) . C('TMPL_CACHE_SUFFIX');
        if (!Storage::has($tmplCacheFile)) {
            return false;
        } elseif (filemtime($tmplTemplateFile) > Storage::get($tmplCacheFile, 'mtime')) {
            return false;
        } elseif (time() > Storage::get($tmplCacheFile, 'mtime') + C('TMPL_CACHE_EXPIRE')) {
            return false;
        }
        return true;
    }

    /**
     * 检查缓存内容是否有效
     *
     * @param $tmplContent
     * @return bool
     */
    protected function checkContentCache($tmplContent)
    {
        $tmplCacheFile = C('TMPL_CACHE_PATH') . md5($tmplContent) . C('TMPL_CACHE_SUFFIX');
        if (Storage::has($tmplCacheFile)) {
            return true;
        } else {
            return false;
        }
    }
}
