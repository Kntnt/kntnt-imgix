<div class="wrap">
  <h2><?php echo $title; ?></h2>
  <form method="post">
    <?php echo wp_nonce_field($ns); ?>
    <table class="form-table">
      <tbody>
        <?php foreach ($fields as $id => $field): ?>
          <tr valign="top">
            <th scope="row"><?php echo $field['label'] ?></th>
            <td>
              <?php switch ($field['type']):
                    case 'text': ?>
                <input id="<?php echo $id ?>" type="text" name="<?php echo "{$ns}[$id]" ?>" value="<?php echo $values[$id]; ?>">
                <br>
                <?php break; ?>
              <?php case 'checkbox': ?>
                <input id="<?php echo $id ?>" type="checkbox" name="<?php echo "{$ns}[$id]" ?>" value = "1" <?php checked($values[$id]); ?>>
                <?php break; ?>
              <?php case 'select': ?>
                <select id="<?php echo $id ?>" name="<?php echo "{$ns}[$id]" ?>">
                <?php foreach ($field['options'] as $value => $item): ?>
                  <option value="<?php echo $value; ?>" <?php echo $value === $values[$id] ? 'selected' : ''; ?>><?php echo $item; ?></option>
                <?php endforeach; ?>
                </select>
                <br>
                <?php break; ?>
              <?php case 'select multiple': ?>
                <select id="<?php echo $id ?>" name="<?php echo "{$ns}[$id][]" ?>" multiple>
                <?php foreach ($field['options'] as $value => $item): ?>
                  <option value="<?php echo $value; ?>" <?php echo in_array($value, $values[$id]) ? 'selected' : ''; ?>><?php echo $item; ?></option>
                <?php endforeach; ?>
                </select>
                <br>
                <?php break; ?>
              <?php case 'html': ?>
                <div id="<?php echo $id ?>"><?php echo $field['html']; ?></div>
                <?php break; ?>
              <?php endswitch; ?>
              <?php if (isset($field['description'])): ?>
                <span class="description"><?php echo $field['description']; ?></span>
              <?php endif; ?>
            </td>
          </tr>           
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="submit"><input id="submit" class="button button-primary" type="submit" name="submit" value="<?php _e('Save Changes', 'kntnt-parallax-images'); ?>"></p>
  </form>
</div>
