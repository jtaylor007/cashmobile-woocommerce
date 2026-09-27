jQuery(function($) {
    var mediaUploader;

    $('.cashmobile-icon-upload').each(function() {
        var $input = $(this);
        var $uploadButton = $('<button class="button button-secondary cashmobile-icon-upload-button" type="button">Upload Icon</button>');
        var $removeButton = $('<button class="button button-secondary custom-icon-remove-button" type="button">Remove Icon</button>');
        var $preview = $('<img class="custom-icon-preview" src="" style="max-width: 100px; max-height: 100px; display: none;">');
        var $previewContainer = $('<div class="custom-icon-preview-container" style="margin-top: 10px;"></div>');

        $input.after($previewContainer);
        $previewContainer.append($preview);
        $input.after($removeButton);
        $input.after($uploadButton);

        function updateButtonsVisibility() {
            if ($input.val()) {
                $uploadButton.hide();
                $removeButton.show();
                $preview.attr('src', $input.val()).show();
            } else {
                $uploadButton.show();
                $removeButton.hide();
                $preview.hide();
            }
        }

        updateButtonsVisibility();

        $uploadButton.on('click', function(e) {
            e.preventDefault();

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media({
                title: 'Choose Icon',
                button: {
                    text: 'Choose Icon'
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $input.val(attachment.url).trigger('change');
                updateButtonsVisibility();
            });

            mediaUploader.open();
        });

        $removeButton.on('click', function(e) {
            e.preventDefault();
            $input.val('').trigger('change');
            updateButtonsVisibility();
        });
    });
});