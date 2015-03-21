<?php

/**
 * @file
 * Contains \Drupal\rules\Context\Context.
 */

namespace Drupal\rules\Context;

use \Drupal\Core\Plugin\Context\Context as CoreContext;

/**
 * Class Context.
 */
class Context extends CoreContext {

  /**
   * Returns whether the context has data associated with it.
   *
   * Note that the value of the data can be NULL.
   *
   * @return bool
   *   Whether the context has data associated with it.
   */
  public function hasContextData() {
    return isset($this->contextData);
  }

}