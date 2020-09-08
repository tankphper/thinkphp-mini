<?php
namespace Think\Event;

class ContentReplace implements EventInterface
{

    /**
     * 执行入口
     *
     * @param $content
     * @return mixed|void
     */
    public function run(&$content)
    {
        $content = $this->templateContentReplace($content);
    }

    /**
     * 模板内容替换
     *
     * @param $content
     * @return mixed
     */
    protected function templateContentReplace($content)
    {
        $replace = [
            '__ROOT__'       => __ROOT__,
            '__APP__'        => __APP__,
            '__MODULE__'     => __MODULE__,
            '__ACTION__'     => __ACTION__,
            '__CONTROLLER__' => __CONTROLLER__,
            '__URL__'        => __CONTROLLER__
        ];
        if (is_array(C('TMPL_REPLACE_MAP'))) {
            $replace = array_merge($replace, C('TMPL_REPLACE_MAP'));
        }
        $content = str_replace(array_keys($replace), array_values($replace), $content);
        return $content;
    }
}