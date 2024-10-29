<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Dropzone\File\Wrapper;

/**
 * ---
 * description: >
 *   Example for rendering a file dropzone wrapper with clear buttons.
 *
 * expected output: >
 *   ILIAS shows a blue box titled "Drag and drop files onto me!". If you drag a file into the box a small window opens
 *   including three buttons named "Save","Close" and "Clear files!". Clicking the clear button will remove the file.
 *   The file will be shown too. You can remove an uploaded file by clicking onto the "X" on the right side.
 * ---
 */
function with_clear_button()
{
    global $DIC;

    $factory = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();
    $request = $DIC->http()->request();

    $submit_flag = 'dropzone_wrapper_with_clear_button';
    $post_url = "{$request->getUri()}&$submit_flag";

    $dropzone = $factory
        ->dropzone()->file()->wrapper(
            'Upload your files here',
            $post_url,
            $factory->messageBox()->info('Drag and drop files onto me!'),
            $factory->input()->field()->file(
                new \ilUIAsyncDemoFileUploadHandlerGUI(),
                'Your files'
            )
        );

    $dropzone = $dropzone->withActionButtons([
        $factory->button()->standard('Clear files!', '#')->withOnClick($dropzone->getClearSignal())
    ]);

    return $renderer->render($dropzone);
}
