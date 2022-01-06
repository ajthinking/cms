<?php

namespace Statamic\Tags\Concerns;

use Illuminate\Support\MessageBag;
use Statamic\Support\Html;

trait RendersForms
{
    use RendersAttributes;

    /**
     * Open a form.
     *
     * @param  string  $action
     * @param  string  $method
     * @param  array  $knownTagParams
     * @param  array  $additionalAttrs
     * @return string
     */
    protected function formOpen($action, $method = 'POST', $knownTagParams = [], $additionalAttrs = [])
    {
        $formMethod = $method === 'GET' ? 'GET' : 'POST';

        $attrs = array_merge([
            'method' => $formMethod,
            'action' => $action,
        ], $additionalAttrs);

        if ($this->params->bool('files')) {
            $attrs['enctype'] = 'multipart/form-data';
        }

        $attrs = $this->renderAttributes($attrs);
        $paramAttrs = $this->renderAttributesFromParams(array_merge(['method', 'action'], $knownTagParams));

        $html = collect(['<form', $attrs, $paramAttrs])->filter()->implode(' ').'>';
        $html .= csrf_field();

        $method = strtoupper($method);

        if (! in_array($method, ['GET', 'POST'])) {
            $html .= method_field($method);
        }

        return $html;
    }

    protected function formMetaFields($meta)
    {
        return collect($meta)
            ->map(function ($value, $key) {
                return sprintf('<input type="hidden" name="_%s" value="%s" />', $key, $value);
            })
            ->implode("\n");
    }

    /**
     * Close a form.
     *
     * @return string
     */
    protected function formClose()
    {
        return '</form>';
    }

    /**
     * Get field with extra data for rendering.
     *
     * @param  \Statamic\Fields\Field  $field
     * @param  string  $errorBag
     * @param  bool|string  $jsDriver
     * @param  array  $jsOptions
     * @return array
     */
    protected function getRenderableField($field, $errorBag = 'default', $jsDriver = false, $jsOptions = [])
    {
        $errors = session('errors') ? session('errors')->getBag($errorBag) : new MessageBag;

        $data = array_merge($field->toArray(), [
            'error' => $errors->first($field->handle()) ?: null,
            'old' => old($field->handle()),
            'alpine' => $jsDriver === 'alpine',
        ]);

        if ($jsDriver === 'alpine') {
            $data['alpine_data_key'] = $this->getAlpineXDataKey($data['handle'], $jsOptions[0] ?? null);
            $data['show_field'] = $this->renderAlpineShowFieldJs($field->conditions(), $jsOptions[0] ?? null);
        }

        $data['field'] = $this->minifyFieldHtml(view($field->fieldtype()->view(), $data)->render());

        return $data;
    }

    /**
     * Render alpine x-data string for fields, with scope if necessary.
     *
     * @param  \Statamic\Fields\Fields  $fields
     * @param  bool|string  $alpineScope
     * @return string
     */
    protected function renderAlpineXData($fields, $alpineScope)
    {
        $oldValues = collect(old());

        $xData = $fields->preProcess()->values()
            ->map(function ($defaultProcessedValue, $handle) use ($oldValues) {
                return $oldValues->has($handle)
                    ? $oldValues->get($handle)
                    : $defaultProcessedValue;
            })
            ->all();

        if (is_string($alpineScope)) {
            $xData = [
                $alpineScope => $xData,
            ];
        }

        return $this->jsonEncodeForHtmlAttribute($xData);
    }

    /**
     * Get alpine x-data key, with scope if necessary.
     *
     * @param  string  $fieldHandle
     * @param  bool|string  $alpineScope
     * @return string
     */
    protected function getAlpineXDataKey($fieldHandle, $alpineScope)
    {
        return is_string($alpineScope)
            ? "{$alpineScope}.{$fieldHandle}"
            : $fieldHandle;
    }

    /**
     * Render alpine `x-if` show field JS logic.
     *
     * @param  array  $conditions
     * @param  string  $alpineScope
     * @return string
     */
    protected function renderAlpineShowFieldJs($conditions, $alpineScope)
    {
        $attrFriendlyConditions = $this->jsonEncodeForHtmlAttribute($conditions);

        $data = '$data';

        if (is_string($alpineScope)) {
            $data .= ".{$alpineScope}";
        }

        return 'Statamic.$conditions.showField('.$attrFriendlyConditions.', '.$data.')';
    }

    /**
     * Json encode for html attribute.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function jsonEncodeForHtmlAttribute($value)
    {
        return str_replace('"', '\'', json_encode($value));
    }

    /**
     * Minify field html.
     *
     * @param  string  $html
     * @return string
     */
    protected function minifyFieldHtml($html)
    {
        // Trim whitespace between elements.
        $html = preg_replace('/>\s*([^<>]*)\s*</', '>$1<', $html);

        return $html;
    }
}
