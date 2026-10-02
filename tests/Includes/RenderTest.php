<?php

/**
 * Tests the FlightPath render and form API helpers.
 */
class RenderTest extends FlightPathTestCase
{
  /**
   * Verifies that fp_render_element() renders plain markup without the
   * normal form-element wrappers.
   */
  public function testRenderElementRendersMarkup()
  {
    $html = fp_render_element('message', [
      'type' => 'markup',
      'value' => '<p>Hello world</p>',
    ]);

    $this->assertStringContainsString('<p>Hello world</p>', $html);
    $this->assertStringContainsString('markup-element', $html);
  }

  /**
   * Verifies that markup_no_wrappers returns the value directly without
   * adding the normal wrapper elements.
   */
  public function testRenderElementRendersMarkupWithoutWrappers()
  {
    $html = fp_render_element('message', [
      'type' => 'markup_no_wrappers',
      'value' => '<span>Hello</span>',
    ]);

    $this->assertSame('<span>Hello</span>', $html);
  }

  /**
   * Verifies that text fields receive the expected input element, machine
   * readable ID, value, size, and maxlength attributes.
   */
  public function testRenderElementRendersTextfield()
  {
    $html = fp_render_element('first_name', [
      'type' => 'textfield',
      'label' => 'First Name',
      'value' => 'Richard',
      'size' => 30,
      'maxlength' => 100,
    ]);

    $this->assertStringContainsString('<label>First Name</label>', $html);
    $this->assertStringContainsString("type='text'", $html);
    $this->assertStringContainsString("name='first_name'", $html);
    $this->assertStringContainsString("value='Richard'", $html);
    $this->assertStringContainsString("size='30'", $html);
    $this->assertStringContainsString("maxlength='100'", $html);
  }

  /**
   * Verifies that text field values are HTML-escaped before being placed
   * into the value attribute.
   */
  public function testRenderElementEscapesTextfieldValue()
  {
    $html = fp_render_element('name', [
      'type' => 'textfield',
      'value' => '"<script>alert(1)</script>',
    ]);

    $this->assertStringContainsString('&quot;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    $this->assertStringNotContainsString('<script>', $html);
  }

  /**
   * Verifies that a required text field displays the required asterisk.
   */
  public function testRenderElementDisplaysRequiredAsterisk()
  {
    $html = fp_render_element('email', [
      'type' => 'textfield',
      'label' => 'Email',
      'required' => TRUE,
    ]);

    $this->assertStringContainsString("<span class='form-required-ast'>*</span>", $html);
    $this->assertStringContainsString('<label>', $html);
    $this->assertStringContainsString('Email', $html);
  }

  /**
   * Verifies that hidden and value elements are rendered as hidden inputs
   * and retain their respective data-type values.
   */
  public function testRenderElementRendersHiddenAndValueElements()
  {
    $hidden = fp_render_element('token', [
      'type' => 'hidden',
      'value' => 'abc123',
    ]);

    $value = fp_render_element('protected_value', [
      'type' => 'value',
      'value' => 'expected',
    ]);

    $this->assertStringContainsString("type='hidden'", $hidden);
    $this->assertStringContainsString("name='token'", $hidden);
    $this->assertStringContainsString("value='abc123'", $hidden);
    $this->assertStringContainsString("data-type='hidden'", $hidden);

    $this->assertStringContainsString("name='protected_value'", $value);
    $this->assertStringContainsString("value='expected'", $value);
    $this->assertStringContainsString("data-type='value'", $value);
  }


  /**
   * A hidden element with a zero value must keep "0" (e.g. alerts exclude_advisor),
   * otherwise it posts '' and strict-mode MySQL rejects it for integer columns.
   */
  public function testRenderElementHiddenKeepsZeroValue()
  {
    $str0 = fp_render_element('exclude_advisor', ['type' => 'hidden', 'value' => '0']);
    $int0 = fp_render_element('exclude_advisor', ['type' => 'hidden', 'value' => 0]);
    $null = fp_render_element('exclude_advisor', ['type' => 'hidden', 'value' => NULL]);

    $this->assertStringContainsString("value='0'", $str0);
    $this->assertStringContainsString("value='0'", $int0);
    $this->assertStringContainsString("value=''", $null);
  }



  /**
   * Verifies that a checkbox renders its checked state based on its value.
   */
  public function testRenderElementRendersCheckbox()
  {
    $checked = fp_render_element('enabled', [
      'type' => 'checkbox',
      'label' => 'Enabled',
      'value' => TRUE,
    ]);

    $unchecked = fp_render_element('enabled', [
      'type' => 'checkbox',
      'label' => 'Enabled',
      'value' => FALSE,
    ]);

    $this->assertStringContainsString("type='checkbox'", $checked);
    $this->assertStringContainsString("value='1'", $checked);
    $this->assertStringContainsString('checked=checked', $checked);

    $this->assertStringContainsString("type='checkbox'", $unchecked);
    $this->assertStringNotContainsString('checked=checked', $unchecked);
  }

  /**
   * Verifies that select elements include the default Please select option
   * unless no_please_select is enabled.
   */
  public function testRenderElementRendersSelect()
  {
    $html = fp_render_element('color', [
      'type' => 'select',
      'label' => 'Color',
      'value' => 'blue',
      'options' => [
        'red' => 'Red',
        'blue' => 'Blue',
      ],
    ]);

    $this->assertStringContainsString("<select name='color'", $html);
    $this->assertStringContainsString('- Please select -', $html);
    $this->assertStringContainsString("<option value='red' >Red</option>", $html);
    $this->assertStringContainsString("<option value='blue' selected>Blue</option>", $html);
  }

  /**
   * Verifies that no_please_select suppresses the automatically generated
   * Please select option.
   */
  public function testRenderElementCanHidePleaseSelect()
  {
    $html = fp_render_element('color', [
      'type' => 'select',
      'options' => [
        'red' => 'Red',
        'blue' => 'Blue',
      ],
      'no_please_select' => TRUE,
    ]);

    $this->assertStringNotContainsString('- Please select -', $html);
    $this->assertStringContainsString("<option value='red' >Red</option>", $html);
  }

  /**
   * Verifies that select elements support one level of option groups.
   */
  public function testRenderElementRendersSelectOptionGroups()
  {
    $html = fp_render_element('course', [
      'type' => 'select',
      'options' => [
        'Fall' => [
          '101' => 'Introduction',
          '201' => 'Intermediate',
        ],
      ],
    ]);

    $this->assertStringContainsString("<optgroup label='Fall'>", $html);
    $this->assertStringContainsString("<option value='101' >Introduction</option>", $html);
    $this->assertStringContainsString("<option value='201' >Intermediate</option>", $html);
  }

  /**
   * Verifies that radio elements mark the matching scalar value as checked.
   */
  public function testRenderElementRendersRadios()
  {
    $html = fp_render_element('status', [
      'type' => 'radios',
      'value' => 'active',
      'options' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
      ],
    ]);

    $this->assertStringContainsString("type='radio'", $html);
    $this->assertStringContainsString("value='active' checked=checked", $html);
    $this->assertStringContainsString("value='inactive'", $html);
  }

  /**
   * Verifies that multiple checkboxes use array-style field names and mark
   * matching values as checked.
   */
  public function testRenderElementRendersCheckboxes()
  {
    $html = fp_render_element('permissions', [
      'type' => 'checkboxes',
      'value' => [
        'read' => 'read',
      ],
      'options' => [
        'read' => 'Read',
        'write' => 'Write',
      ],
    ]);

    $this->assertStringContainsString("type='checkbox'", $html);
    $this->assertStringContainsString("name='permissions[read]'", $html);
    $this->assertStringContainsString("value='read' checked=checked", $html);
    $this->assertStringContainsString("name='permissions[write]'", $html);
  }

  /**
   * Verifies that file elements always receive [] in their name, as required
   * by the FlightPath upload handling.
   */
  public function testRenderElementRendersFileInputAsArray()
  {
    $html = fp_render_element('document', [
      'type' => 'file',
    ]);

    $this->assertStringContainsString("type='file'", $html);
    $this->assertStringContainsString("name='document[]'", $html);
  }

  /**
   * Verifies that submit elements are rendered with their supplied value.
   */
  public function testRenderElementRendersSubmitButton()
  {
    $html = fp_render_element('submit', [
      'type' => 'submit',
      'value' => 'Save',
    ]);

    $this->assertStringContainsString("type='submit'", $html);
    $this->assertStringContainsString("name='submit'", $html);
    $this->assertStringContainsString("value='Save'", $html);
  }

  /**
   * Verifies that do_not_render suppresses the element completely.
   */
  public function testRenderElementDoesNotRenderDoNotRenderElements()
  {
    $html = fp_render_element('secret', [
      'type' => 'do_not_render',
      'value' => 'Should not appear',
    ]);

    $this->assertEmpty($html);
  }

  /**
   * Verifies that render arrays are ordered by ascending weight while
   * preserving the keys used to identify their elements.
   */
  public function testRenderArrayOrdersElementsByWeight()
  {
    $render_array = [
      'third' => [
        'type' => 'markup',
        'value' => 'THIRD',
        'weight' => 20,
      ],
      'first' => [
        'type' => 'markup',
        'value' => 'FIRST',
        'weight' => -10,
      ],
      'second' => [
        'type' => 'markup',
        'value' => 'SECOND',
        'weight' => 5,
      ],
      '#id' => 'render_order_test',
    ];

    $html = fp_render_content($render_array, FALSE);

    $first_position = strpos($html, 'FIRST');
    $second_position = strpos($html, 'SECOND');
    $third_position = strpos($html, 'THIRD');

    $this->assertNotFalse($first_position);
    $this->assertNotFalse($second_position);
    $this->assertNotFalse($third_position);

    $this->assertTrue($first_position < $second_position);
    $this->assertTrue($second_position < $third_position);
  }

  /**
   * Verifies that fieldsets render their child elements inside the expected
   * fieldset markup.
   */
  public function testRenderArrayRendersFieldset()
  {
    $render_array = [
      'details' => [
        'type' => 'fieldset',
        'label' => 'Details',
        'elements' => [
          [
            'name' => [
              'type' => 'textfield',
              'label' => 'Name',
              'value' => 'Richard',
            ],
          ],
        ],
      ],
    ];

    $html = fp_render_array($render_array);

    $this->assertStringContainsString("<fieldset class='fp-fieldset'", $html);
    $this->assertStringContainsString('<legend>Details</legend>', $html);
    $this->assertStringContainsString('Name', $html);
    $this->assertStringContainsString("value='Richard'", $html);
  }

  /**
   * Verifies that fp_render_content() supplies its outer wrapper by default.
   */
  public function testRenderContentIncludesWrapperByDefault()
  {
    $html = fp_render_content([
      '#id' => 'test_render_content',
      'message' => [
        'type' => 'markup_no_wrappers',
        'value' => 'Hello',
      ],
    ]);

    $this->assertStringContainsString("class='renderapi-content ", $html);
    $this->assertStringContainsString("id='render-test_render_content'", $html);
    $this->assertStringContainsString('Hello', $html);
    $this->assertStringContainsString('</div>', $html);
  }

  /**
   * Verifies that fp_render_content() can omit its outer wrapper when
   * explicitly requested.
   */
  public function testRenderContentCanOmitWrapper()
  {
    $html = fp_render_content([
      '#id' => 'test_render_content',
      'message' => [
        'type' => 'markup_no_wrappers',
        'value' => 'Hello',
      ],
    ], FALSE);

    $this->assertSame('Hello', $html);
  }

  /**
   * Verifies that the #id is passed through FlightPath's machine-readable
   * conversion before being used in the render wrapper ID.
   */
  public function testRenderContentUsesMachineReadableId()
  {
    $html = fp_render_content([
      '#id' => 'My Test Render',
      'message' => [
        'type' => 'markup_no_wrappers',
        'value' => 'Content',
      ],
    ]);

    $this->assertStringContainsString("id='render-My_Test_Render'", $html);
  }

  /**
   * Verifies that fp_get_form() adds default submit and validation handlers
   * and orders form elements by ascending weight.
   */
  public function testGetFormAddsDefaultHandlersAndOrdersElements()
  {
    $form = fp_get_form('render_test_form');

    $this->assertSame(['render_test_form_submit', 'weight' => 0, ], $form['#submit_handlers']);

    $this->assertSame(['render_test_form_validate', 'weight' => 0,], $form['#validate_handlers']);

    $keys = array_keys($form);

    $this->assertSame('first', $keys[0]);
    $this->assertSame('#submit_handlers', $keys[1]);
    $this->assertSame('#validate_handlers', $keys[2]);
    $this->assertSame('second', $keys[3]);
    $this->assertSame('third', $keys[4]);
  }

  /**
   * Verifies that a form which already supplies handlers keeps those handlers
   * rather than having the defaults replace them.
   */
  public function testGetFormPreservesExistingHandlers()
  {
    $form = fp_get_form('render_test_form_with_handlers');

    $this->assertSame(['custom_submit', 'weight' => 0,], $form['#submit_handlers']);
    $this->assertSame(['custom_validate', 'weight' => 0,], $form['#validate_handlers'] );
  }

  /**
   * Verifies that required fields with blank submitted values generate a
   * form error.
   */
  public function testFormBasicValidateRejectsBlankRequiredValue()
  {
    $_SESSION['fp_form_errors'] = [];

    $form = [
      'name' => [
        'type' => 'textfield',
        'label' => 'Name',
        'required' => TRUE,
      ],
    ];

    form_basic_validate($form, [
      'values' => [
        'name' => '',
      ],
    ]);

    $this->assertTrue(form_has_errors());
    $this->assertSame('name', $_SESSION['fp_form_errors'][0]['name']);
  }

  /**
   * Verifies that a required field with a value does not generate an error.
   */
  public function testFormBasicValidateAcceptsRequiredValue()
  {
    unset($_SESSION['fp_form_errors']);

    $form = [
      'name' => [
        'type' => 'textfield',
        'label' => 'Name',
        'required' => TRUE,
      ],
    ];

    form_basic_validate($form, [
      'values' => [
        'name' => 'Richard',
      ],
    ]);

    $this->assertFalse(form_has_errors());
  }

  /**
   * Verifies that value elements protect server-defined values from being
   * changed in the submitted form state.
   */
  public function testFormBasicValidateRejectsChangedValueElement()
  {
    $_SESSION['fp_form_errors'] = [];

    $form = [
      'protected' => [
        'type' => 'value',
        'value' => 'expected',
      ],
    ];

    form_basic_validate($form, [
      'values' => [
        'protected' => 'tampered',
      ],
    ]);

    $this->assertTrue(form_has_errors());
    $this->assertSame('', $_SESSION['fp_form_errors'][0]['name']);
  }

  /**
   * Verifies that matching value elements pass validation.
   */
  public function testFormBasicValidateAcceptsMatchingValueElement()
  {
    unset($_SESSION['fp_form_errors']);

    $form = [
      'protected' => [
        'type' => 'value',
        'value' => 'expected',
      ],
    ];

    form_basic_validate($form, [
      'values' => [
        'protected' => 'expected',
      ],
    ]);

    $this->assertFalse(form_has_errors());
  }

  /**
   * Verifies that form_error() records the error in the session and marks
   * validation as failed.
   */
  public function testFormErrorRecordsSessionError()
  {
    unset($_SESSION['fp_form_errors']);

    form_error('name', 'Name is required.');

    $this->assertTrue(form_has_errors());
    $this->assertSame('name', $_SESSION['fp_form_errors'][0]['name']);
    $this->assertSame('Name is required.', $_SESSION['fp_form_errors'][0]['msg']);
  }

  /**
   * Verifies that form_error() can request that later validators stop running.
   */
  public function testFormErrorCanStopFurtherValidators()
  {
    unset($_SESSION['fp_form_errors']);
    unset($GLOBALS['form_error_stop_further_validators']);

    form_error('name', 'Fatal validation error.', TRUE);

    $this->assertTrue(form_has_errors());
    $this->assertTrue($GLOBALS['form_error_stop_further_validators']);
  }

  /**
   * Verifies the three basic states recognized by form_has_errors().
   */
  public function testFormHasErrorsRecognizesSessionStates()
  {
    unset($_SESSION['fp_form_errors']);
    $this->assertFalse(form_has_errors());

    $_SESSION['fp_form_errors'] = [];
    $this->assertFalse(form_has_errors());

    $_SESSION['fp_form_errors'] = [
      [
        'name' => 'field',
        'msg' => 'Invalid value.',
      ],
    ];

    $this->assertTrue(form_has_errors());
  }

  /**
   * Verifies that clear_session_form_values() removes saved submission
   * values for one callback without disturbing other callbacks.
   */
  public function testClearSessionFormValuesRemovesOnlySpecifiedCallback()
  {
    $_SESSION['fp_form_submissions'] = [
      'form_one' => [
        'values' => [
          'name' => 'Richard',
        ],
      ],
      'form_two' => [
        'values' => [
          'name' => 'Alex',
        ],
      ],
    ];

    clear_session_form_values('form_one');

    $this->assertArrayNotHasKey('form_one', $_SESSION['fp_form_submissions']);
    $this->assertArrayHasKey('form_two', $_SESSION['fp_form_submissions']);
    $this->assertSame('Alex', $_SESSION['fp_form_submissions']['form_two']['values']['name']);
  }
}


/**
 * Test form callback used by fp_get_form().
 */
function render_test_form(): array
{
  return [
    'third' => [
      'type' => 'textfield',
      'weight' => 20,
    ],
    'first' => [
      'type' => 'textfield',
      'weight' => -10,
    ],
    'second' => [
      'type' => 'textfield',
      'weight' => 5,
    ],
  ];
}


/**
 * Test form callback that supplies its own submit and validation handlers.
 */
function render_test_form_with_handlers(): array
{
  return [
    '#submit_handlers' => ['custom_submit'],
    '#validate_handlers' => ['custom_validate'],
    'field' => [
      'type' => 'textfield',
    ],
  ];
}