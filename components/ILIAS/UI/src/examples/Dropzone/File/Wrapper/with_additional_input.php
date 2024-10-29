<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Dropzone\File\Wrapper;

/**
 * ---
 * description: >
 *   Example for rendering a file dropzone wrapper with additional input.
 *
 * expected output: >
 *   ILIAS shows a blue box titled "Drag and drop files onto me!". If you drag a file into the box a small window opens
 *   including two buttons named "Save" and "Close". The file will be shown too. Additionally a text input titled "Additional
 *   Input" is rendered below the file entry. Below the input "Additional input which affects all files of this upload" is
 *   written. You can remove an uploaded file by clicking onto the "X" on the right side.
 * ---
 */
function with_additional_input()
{
    global $DIC;

    $factory = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();
    $request = $DIC->http()->request();
    $wrapper = $DIC->http()->wrapper()->query();

    $submit_flag = 'dropzone_wrapper_with_additional_input';
    $post_url = "{$request->getUri()}&$submit_flag";

    $dropzone = $factory
        ->dropzone()->file()->wrapper(
            'Upload your files here',
            $post_url,
            $factory->messageBox()->info('Drag and drop files onto me!'),
            $factory->input()->field()->file(
                new \ilUIAsyncDemoFileUploadHandlerGUI(),
                'Your files'
            ),
            $factory->input()->field()->text(
                'Additional Input',
                'Additional input which affects all files of this upload.'
            )
        );

    // please use ilCtrl to generate an appropriate link target
    // and check it's command instead of this.
    if ($wrapper->has($submit_flag)) {
        $dropzone = $dropzone->withRequest($request);
        $data = $dropzone->getData();
    } else {
        $data = 'no results yet.';
    }

    return '<pre>' . print_r($data, true) . '</pre>' .
        $renderer->render($dropzone);
}
