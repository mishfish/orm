<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Command;

use Spiral\ORM\Context\ConsumerInterface;

/**
 * Command provide the ability to accept and carry the context to the persistence layer.
 */
interface ContextCarrierInterface extends CommandInterface, ConsumerInterface
{
    /**
     * Wait for the context value. Command must not be ready until the value come if value is required.
     *
     * @param string $key
     * @param bool   $required
     */
    public function waitContext(string $key, bool $required = true);

    /**
     * Get current command context.
     *
     * @return array
     */
    public function getContext(): array;
}