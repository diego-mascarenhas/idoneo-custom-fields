/* global jQuery, ICF_FIELDS, wp */
(function ($) {
    'use strict';

    var CFG = ICF_FIELDS || {};
    var PH = CFG.rowPlaceholder || '__icf_row__';

    function uniqueIndex() {
        return 'r' + Date.now() + Math.floor(Math.random() * 1000);
    }

    function instantiateTemplate(html) {
        // Replace the row placeholder with a unique index so names stay distinct.
        return html.split(PH).join(uniqueIndex());
    }

    $(function () {
        initColorPickers($(document));
        initSortable($(document));
        evaluateConditionals($(document));

        // ---- Repeater ----
        $(document).on('click', '.icf-repeater-add', function (e) {
            e.preventDefault();
            var $rep = $(this).closest('.icf-repeater');
            var tpl = $rep.children('.icf-repeater-template').html();
            var $row = $(instantiateTemplate(tpl));
            $rep.children('.icf-repeater-rows').append($row);
            initColorPickers($row);
            initSortable($row);
            evaluateConditionals($rep);
        });

        // ---- Flexible content ----
        $(document).on('change', '.icf-flexible-layout', function () {
            var layout = $(this).val();
            if (!layout) {
                return;
            }
            var $flex = $(this).closest('.icf-flexible');
            var tpl = $flex.children('.icf-flexible-template[data-layout="' + layout + '"]').html();
            if (tpl) {
                var $row = $(instantiateTemplate(tpl));
                $flex.children('.icf-flexible-rows').append($row);
                initColorPickers($row);
                initSortable($row);
                evaluateConditionals($flex);
            }
            $(this).val('');
        });

        // ---- Remove a repeater / flexible row ----
        $(document).on('click', '.icf-row-remove', function (e) {
            e.preventDefault();
            $(this).closest('.icf-repeater-row, .icf-flexible-row').remove();
        });

        // ---- Media (image / file) ----
        $(document).on('click', '.icf-media-select', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.icf-media');
            var type = $wrap.data('type');
            var frame = wp.media({
                title: type === 'image' ? CFG.i18n.selectImage : CFG.i18n.selectFile,
                button: { text: CFG.i18n.use },
                multiple: false,
                library: type === 'image' ? { type: 'image' } : {}
            });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                $wrap.find('.icf-media-id').val(att.id);
                var preview = type === 'image'
                    ? '<img src="' + (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url) + '" />'
                    : $('<div>').text(att.filename || att.title).html();
                $wrap.find('.icf-media-preview').html(preview);
                $wrap.find('.icf-media-remove').show();
            });
            frame.open();
        });
        $(document).on('click', '.icf-media-remove', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.icf-media');
            $wrap.find('.icf-media-id').val('');
            $wrap.find('.icf-media-preview').empty();
            $(this).hide();
        });

        // ---- Gallery ----
        $(document).on('click', '.icf-gallery-add', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.icf-gallery');
            var frame = wp.media({ title: CFG.i18n.selectImage, multiple: true, library: { type: 'image' } });
            frame.on('select', function () {
                var sel = frame.state().get('selection').toJSON();
                var $list = $wrap.find('.icf-gallery-list');
                sel.forEach(function (att) {
                    var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                    $list.append('<li data-id="' + att.id + '"><img src="' + url + '" /><button type="button" class="icf-gallery-remove">&times;</button></li>');
                });
                syncGallery($wrap);
            });
            frame.open();
        });
        $(document).on('click', '.icf-gallery-remove', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.icf-gallery');
            $(this).closest('li').remove();
            syncGallery($wrap);
        });

        // ---- Conditional logic re-evaluation ----
        $(document).on('change keyup', '.icf-fields input, .icf-fields select, .icf-fields textarea', function () {
            evaluateConditionals($(this).closest('.icf-fields, .icf-repeater-row, .icf-flexible-row, .icf-group-fields'));
        });
    });

    function syncGallery($wrap) {
        var ids = [];
        $wrap.find('.icf-gallery-list li').each(function () {
            ids.push($(this).data('id'));
        });
        $wrap.find('.icf-gallery-ids').val(ids.join(','));
    }

    function initColorPickers($scope) {
        if ($.fn.wpColorPicker) {
            $scope.find('.icf-color-picker').wpColorPicker();
        }
    }

    function initSortable($scope) {
        if (!$.fn.sortable) {
            return;
        }
        $scope.find('.icf-repeater-rows').each(function () {
            if (!$(this).data('icf-sortable')) {
                $(this).sortable({ handle: '.icf-row-handle', items: '> .icf-repeater-row', axis: 'y' }).data('icf-sortable', true);
            }
        });
        $scope.find('.icf-flexible-rows').each(function () {
            if (!$(this).data('icf-sortable')) {
                $(this).sortable({ handle: '.icf-row-handle', items: '> .icf-flexible-row', axis: 'y' }).data('icf-sortable', true);
            }
        });
    }

    /**
     * Show/hide fields that declare conditional logic based on a sibling field value.
     */
    function evaluateConditionals($scope) {
        var $container = $scope && $scope.length ? $scope : $(document);
        $container.find('.icf-field[data-cond-field]').each(function () {
            var $field = $(this);
            var target = $field.data('cond-field');
            var expected = String($field.data('cond-value'));
            // Look for the controlling field within the nearest shared fields container.
            var $context = $field.closest('.icf-row-fields, .icf-group-fields, .icf-fields');
            var $ctrl = $context.find('.icf-field[data-name="' + target + '"]').first();
            if (!$ctrl.length) {
                return;
            }
            var actual = readFieldValue($ctrl);
            var match = Array.isArray(actual) ? actual.map(String).indexOf(expected) !== -1 : String(actual) === expected;
            $field.toggle(match);
        });
    }

    function readFieldValue($field) {
        var $inputs = $field.find('.icf-input').first().find('input, select, textarea');
        var checkboxes = $inputs.filter(':checkbox');
        if (checkboxes.length) {
            var vals = [];
            checkboxes.filter(':checked').each(function () { vals.push($(this).val()); });
            return vals;
        }
        var radios = $inputs.filter(':radio');
        if (radios.length) {
            return radios.filter(':checked').val() || '';
        }
        return $inputs.first().val();
    }
})(jQuery);
