<?php
class vAdminExtensions {
  public static function vCheckBox($args) {
		$name = $args['name'];
		$id = 'js'.$name;
    $labelText = $args['label'];
    $tipElement = isset($args['tip']) ? "<small class='form-text text-muted' style='margin-top: -8px;'>".$args['tip']."</small>" : '';

		if (isset($args['id'])) {
			$id = $args['id'];
		}
		$disabled = isset($args['disabled'])?'disabled':'';

		$labelClass = '';
		if (isset($args['labelClass'])) {
			$labelClass = $args['labelClass'];
		}

		$checked = '';
    
    if(isset($args['checked'])) {
      if ($args['checked']) {
        $checked = 'checked';
      }
    }

return <<<EOF
  <div class="form-check">
    <label class="v-checkbox $labelClass">
      <input type="checkbox" id="$id" name="$name" $checked $disabled>
      <svg viewBox="0 0 64 64">
        <path d="M 0 16 V 56 A 8 8 90 0 0 8 64 H 56 A 8 8 90 0 0 64 56 V 8 A 8 8 90 0 0 56 0 H 8 A 8 8 90 0 0 0 8 V 16 L 32 48 L 64 16 V 8 A 8 8 90 0 0 56 0 H 8 A 8 8 90 0 0 0 8 V 56 A 8 8 90 0 0 8 64 H 56 A 8 8 90 0 0 64 56 V 16" pathLength="575.0541381835938" class="path"></path>
      </svg>

      <span class="label-text">$labelText</span>
    </label>
    $tipElement
  </div>
EOF;
  }
}

?>