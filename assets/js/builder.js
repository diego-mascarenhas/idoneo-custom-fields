/* global jQuery, ICF_BUILDER */
(function ($) {
    'use strict';

    var L = ICF_BUILDER || {};
    var T = L.i18n || {};
    var LAYOUT_TYPES = L.layout || ['repeater', 'group', 'flexible_content'];

    function esc(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function typeOptions(selected) {
        var html = '';
        $.each(L.types || {}, function (groupLabel, group) {
            html += '<optgroup label="' + esc(groupLabel) + '">';
            $.each(group, function (key, label) {
                html += '<option value="' + esc(key) + '"' + (key === selected ? ' selected' : '') + '>' + esc(label) + '</option>';
            });
            html += '</optgroup>';
        });
        return html;
    }

    function fieldRow(field) {
        field = field || {};
        var type = field.type || 'text';
        var cond = field.conditional || {};
        var $row = $('<div class="icf-field-row" />');

        var header =
            '<div class="icf-field-head">' +
            '<span class="icf-drag dashicons dashicons-move"></span>' +
            '<span class="icf-field-title">' + (esc(field.label) || T.untitled) + '</span>' +
            '<span class="icf-field-typebadge">' + esc(type) + '</span>' +
            '<span class="icf-field-actions">' +
            '<a href="#" class="icf-toggle-body" title="' + esc(T.collapse) + '">▾</a> ' +
            '<a href="#" class="icf-dup" title="' + esc(T.duplicate) + '">⧉</a> ' +
            '<a href="#" class="icf-del" title="' + esc(T.remove) + '">✕</a>' +
            '</span>' +
            '</div>';

        var body =
            '<div class="icf-field-body">' +
            row(T.label, '<input type="text" class="f-label widefat" value="' + esc(field.label) + '">') +
            row(T.name, '<input type="text" class="f-name widefat" value="' + esc(field.name) + '" placeholder="field_name">') +
            row(T.type, '<select class="f-type widefat">' + typeOptions(type) + '</select>') +
            row(T.instructions, '<textarea class="f-instructions widefat" rows="2">' + esc(field.instructions) + '</textarea>') +
            row('', '<label><input type="checkbox" class="f-required"' + (field.required ? ' checked' : '') + '> ' + esc(T.required) + '</label>') +
            rowCls('icf-r-default', T.default, '<input type="text" class="f-default widefat" value="' + esc(field.default) + '">') +
            rowCls('icf-r-placeholder', T.placeholder, '<input type="text" class="f-placeholder widefat" value="' + esc(field.placeholder) + '">') +
            rowCls('icf-r-choices', T.choices, '<textarea class="f-choices widefat" rows="4">' + esc(field.choices) + '</textarea>') +
            rowCls('icf-r-multiple', '', '<label><input type="checkbox" class="f-multiple"' + (field.multiple ? ' checked' : '') + '> ' + esc(T.multiple) + '</label>') +
            rowCls('icf-r-message', T.message, '<textarea class="f-message widefat" rows="3">' + esc(field.message) + '</textarea>') +
            rowCls('icf-r-posttype', T.post_type, '<input type="text" class="f-posttype widefat" value="' + esc(field.post_type) + '" placeholder="post, page">') +
            rowCls('icf-r-taxonomy', T.taxonomy, '<input type="text" class="f-taxonomy widefat" value="' + esc(field.taxonomy) + '" placeholder="category">') +
            conditionalRow(cond) +
            '<div class="icf-subfields-wrap icf-r-sub">' +
            '<p class="icf-sub-label">' + esc(T.sub_fields) + '</p>' +
            '<div class="icf-subfields"></div>' +
            '<p><button type="button" class="button icf-add-sub">' + esc(T.add_sub) + '</button></p>' +
            '</div>' +
            '<div class="icf-layouts-wrap icf-r-layouts">' +
            '<p class="icf-sub-label">' + esc(T.layouts) + '</p>' +
            '<div class="icf-layouts"></div>' +
            '<p><button type="button" class="button icf-add-layout">' + esc(T.add_layout) + '</button></p>' +
            '</div>' +
            '</div>';

        $row.html(header + body);

        // Hydrate sub fields / layouts.
        if (type === 'repeater' || type === 'group') {
            var $sub = $row.find('> .icf-field-body > .icf-subfields-wrap > .icf-subfields');
            (field.sub_fields || []).forEach(function (sf) {
                $sub.append(fieldRow(sf));
            });
        }
        if (type === 'flexible_content') {
            var $layouts = $row.find('> .icf-field-body > .icf-layouts-wrap > .icf-layouts');
            (field.layouts || []).forEach(function (lay) {
                $layouts.append(layoutRow(lay));
            });
        }

        applyTypeVisibility($row, type);
        return $row;
    }

    function layoutRow(layout) {
        layout = layout || {};
        var $row = $('<div class="icf-layout-row" />');
        $row.html(
            '<div class="icf-layout-head">' +
            '<span class="icf-drag dashicons dashicons-move"></span>' +
            '<input type="text" class="l-label" placeholder="Label" value="' + esc(layout.label) + '">' +
            '<input type="text" class="l-name" placeholder="layout_name" value="' + esc(layout.name) + '">' +
            '<a href="#" class="icf-del-layout" title="' + esc(T.remove) + '">✕</a>' +
            '</div>' +
            '<div class="icf-subfields"></div>' +
            '<p><button type="button" class="button icf-add-sub">' + esc(T.add_sub) + '</button></p>'
        );
        var $sub = $row.find('> .icf-subfields');
        (layout.sub_fields || []).forEach(function (sf) {
            $sub.append(fieldRow(sf));
        });
        return $row;
    }

    function conditionalRow(cond) {
        return '<div class="icf-conditional">' +
            '<label><input type="checkbox" class="f-cond-enable"' + (cond.enabled ? ' checked' : '') + '> ' + esc(T.cond_enable) + '</label>' +
            '<div class="icf-cond-detail"' + (cond.enabled ? '' : ' style="display:none"') + '>' +
            '<input type="text" class="f-cond-field" placeholder="' + esc(T.cond_field) + '" value="' + esc(cond.field) + '"> ' +
            '<span>' + esc(T.cond_value) + '</span> ' +
            '<input type="text" class="f-cond-value" value="' + esc(cond.value) + '">' +
            '</div></div>';
    }

    function row(label, control) {
        return '<div class="icf-r"><label class="icf-r-label">' + esc(label) + '</label><div class="icf-r-control">' + control + '</div></div>';
    }
    function rowCls(cls, label, control) {
        return '<div class="icf-r ' + cls + '"><label class="icf-r-label">' + esc(label) + '</label><div class="icf-r-control">' + control + '</div></div>';
    }

    function applyTypeVisibility($row, type) {
        var body = $row.find('> .icf-field-body');
        var hasChoices = (type === 'select' || type === 'checkbox' || type === 'radio');
        var canMultiple = (type === 'select' || type === 'post_object' || type === 'taxonomy');
        body.find('> .icf-r-choices').toggle(hasChoices);
        body.find('> .icf-r-multiple').toggle(canMultiple);
        body.find('> .icf-r-message').toggle(type === 'message' || type === 'true_false');
        body.find('> .icf-r-posttype').toggle(type === 'post_object');
        body.find('> .icf-r-taxonomy').toggle(type === 'taxonomy');
        body.find('> .icf-r-placeholder').toggle(['text', 'textarea', 'number', 'email', 'url', 'password'].indexOf(type) !== -1);
        body.find('> .icf-r-default').toggle(['text', 'textarea', 'number', 'email', 'url', 'select', 'radio'].indexOf(type) !== -1);
        body.find('> .icf-subfields-wrap').toggle(type === 'repeater' || type === 'group');
        body.find('> .icf-layouts-wrap').toggle(type === 'flexible_content');
    }

    // ---- Serialization (DOM -> JSON) ----

    function serializeList($list) {
        var out = [];
        $list.children('.icf-field-row').each(function () {
            out.push(serializeField($(this)));
        });
        return out;
    }

    function serializeField($row) {
        var body = $row.find('> .icf-field-body');
        var type = body.find('> .icf-r .f-type').val() || 'text';
        var f = {
            label: body.find('> .icf-r .f-label').val() || '',
            name: body.find('> .icf-r .f-name').val() || '',
            type: type,
            instructions: body.find('> .icf-r .f-instructions').val() || '',
            required: body.find('> .icf-r .f-required').is(':checked') ? 1 : 0,
            default: body.find('> .icf-r .f-default').val() || '',
            placeholder: body.find('> .icf-r .f-placeholder').val() || '',
            choices: body.find('> .icf-r .f-choices').val() || '',
            multiple: body.find('> .icf-r .f-multiple').is(':checked') ? 1 : 0,
            message: body.find('> .icf-r .f-message').val() || '',
            post_type: body.find('> .icf-r .f-posttype').val() || '',
            taxonomy: body.find('> .icf-r .f-taxonomy').val() || ''
        };
        if (body.find('> .icf-conditional .f-cond-enable').is(':checked')) {
            f.conditional = {
                enabled: 1,
                field: body.find('> .icf-conditional .f-cond-field').val() || '',
                value: body.find('> .icf-conditional .f-cond-value').val() || ''
            };
        }
        if (type === 'repeater' || type === 'group') {
            f.sub_fields = serializeList(body.find('> .icf-subfields-wrap > .icf-subfields'));
        }
        if (type === 'flexible_content') {
            f.layouts = [];
            body.find('> .icf-layouts-wrap > .icf-layouts > .icf-layout-row').each(function () {
                var $l = $(this);
                f.layouts.push({
                    label: $l.find('> .icf-layout-head .l-label').val() || '',
                    name: $l.find('> .icf-layout-head .l-name').val() || '',
                    sub_fields: serializeList($l.find('> .icf-subfields'))
                });
            });
        }
        return f;
    }

    // ---- Location rules ----

    function ruleRow(rule) {
        rule = rule || { param: 'post_type', operator: '==', value: '' };
        var $row = $('<div class="icf-rule-row" />');
        var paramSel = '<select class="r-param">';
        $.each(L.params || {}, function (k, v) {
            paramSel += '<option value="' + esc(k) + '"' + (k === rule.param ? ' selected' : '') + '>' + esc(v) + '</option>';
        });
        paramSel += '</select>';

        var opSel = '<select class="r-operator">';
        $.each(L.operators || {}, function (k, v) {
            opSel += '<option value="' + esc(k) + '"' + (k === rule.operator ? ' selected' : '') + '>' + esc(v) + '</option>';
        });
        opSel += '</select>';

        $row.html(paramSel + ' ' + opSel + ' <span class="r-value-wrap"></span> <a href="#" class="icf-del-rule">✕</a>');
        renderRuleValue($row, rule.param, rule.value);
        return $row;
    }

    function renderRuleValue($row, param, value) {
        var choices = (L.choices || {})[param] || {};
        var html;
        if (Object.keys(choices).length) {
            html = '<select class="r-value">';
            $.each(choices, function (k, v) {
                html += '<option value="' + esc(k) + '"' + (String(k) === String(value) ? ' selected' : '') + '>' + esc(v) + '</option>';
            });
            html += '</select>';
        } else {
            html = '<input type="text" class="r-value" value="' + esc(value) + '">';
        }
        $row.find('.r-value-wrap').html(html);
    }

    function serializeRules($app) {
        var out = [];
        $app.children('.icf-rule-row').each(function () {
            var $r = $(this);
            out.push({
                param: $r.find('.r-param').val(),
                operator: $r.find('.r-operator').val(),
                value: $r.find('.r-value').val() || ''
            });
        });
        return out;
    }

    // ---- Init ----

    $(function () {
        var $app = $('#icf-builder-app');
        var $loc = $('#icf-location-app');
        if (!$app.length) {
            return;
        }

        var $list = $('<div class="icf-fields-list" />').appendTo($app);

        var initialFields = JSON.parse($('#icf-fields-json').val() || '[]');
        if (!initialFields.length) {
            $app.append('<p class="icf-empty">' + esc(T.no_fields) + '</p>');
        }
        initialFields.forEach(function (f) {
            $list.append(fieldRow(f));
        });

        var initialRules = JSON.parse($('#icf-location-json').val() || '[]');
        if (!initialRules.length) {
            $loc.append('<p class="icf-empty-rule">' + esc(T.no_rules) + '</p>');
        }
        initialRules.forEach(function (r) {
            $loc.append(ruleRow(r));
        });

        makeSortable($list);

        // Add top-level field.
        $('.icf-add-field').on('click', function (e) {
            e.preventDefault();
            $app.find('.icf-empty').remove();
            var $r = fieldRow({ type: 'text' });
            $list.append($r);
            makeSortable($list);
        });

        // Add rule.
        $('.icf-add-rule').on('click', function (e) {
            e.preventDefault();
            $loc.find('.icf-empty-rule').remove();
            $loc.append(ruleRow());
        });

        // Delegated events.
        $app.on('click', '.icf-add-sub', function (e) {
            e.preventDefault();
            var $sub = $(this).closest('.icf-subfields-wrap, .icf-layout-row').find('> .icf-subfields').first();
            $sub.append(fieldRow({ type: 'text' }));
            makeSortable($sub);
        });
        $app.on('click', '.icf-add-layout', function (e) {
            e.preventDefault();
            var $layouts = $(this).closest('.icf-layouts-wrap').find('> .icf-layouts');
            $layouts.append(layoutRow({}));
            var $newSub = $layouts.find('.icf-layout-row:last > .icf-subfields');
            makeSortable($newSub);
        });
        $app.on('click', '.icf-del', function (e) {
            e.preventDefault();
            $(this).closest('.icf-field-row').remove();
        });
        $app.on('click', '.icf-del-layout', function (e) {
            e.preventDefault();
            $(this).closest('.icf-layout-row').remove();
        });
        $app.on('click', '.icf-dup', function (e) {
            e.preventDefault();
            var $orig = $(this).closest('.icf-field-row');
            var data = serializeField($orig);
            $orig.after(fieldRow(data));
        });
        $app.on('click', '.icf-toggle-body', function (e) {
            e.preventDefault();
            $(this).closest('.icf-field-row').toggleClass('icf-collapsed');
        });
        $app.on('change', '.f-type', function () {
            var $row = $(this).closest('.icf-field-row');
            applyTypeVisibility($row, $(this).val());
            $row.find('> .icf-field-head .icf-field-typebadge').text($(this).val());
        });
        $app.on('input', '.f-label', function () {
            var $row = $(this).closest('.icf-field-row');
            $row.find('> .icf-field-head .icf-field-title').text($(this).val() || T.untitled);
            // Auto-fill name if empty.
            var $name = $row.find('> .icf-field-body > .icf-r .f-name');
            if ($name.length && !$name.data('touched') && !$name.val()) {
                $name.val(slugify($(this).val()));
            }
        });
        $app.on('input', '.f-name', function () {
            $(this).data('touched', true);
        });
        $app.on('change', '.f-cond-enable', function () {
            $(this).closest('.icf-conditional').find('.icf-cond-detail').toggle($(this).is(':checked'));
        });

        // Location events.
        $loc.on('change', '.r-param', function () {
            var $row = $(this).closest('.icf-rule-row');
            renderRuleValue($row, $(this).val(), '');
        });
        $loc.on('click', '.icf-del-rule', function (e) {
            e.preventDefault();
            $(this).closest('.icf-rule-row').remove();
        });

        // Serialize on submit.
        $('form#post').on('submit', function () {
            $('#icf-fields-json').val(JSON.stringify(serializeList($list)));
            $('#icf-location-json').val(JSON.stringify(serializeRules($loc)));
        });
    });

    function slugify(s) {
        return (s || '').toString().toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    }

    function makeSortable($container) {
        if (!$container || !$container.sortable) {
            return;
        }
        $container.sortable({
            handle: '> .icf-field-row > .icf-field-head > .icf-drag, > .icf-layout-row > .icf-layout-head > .icf-drag',
            items: '> .icf-field-row, > .icf-layout-row',
            axis: 'y',
            tolerance: 'pointer'
        });
    }
})(jQuery);
