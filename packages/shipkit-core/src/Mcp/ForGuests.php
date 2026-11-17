<?php

namespace ShipKit\Core\Mcp;

use Attribute;

/**
 * Marks an MCP server that signed-out visitors reach, with no authentication middleware on its
 * route. Every tool it registers must be `#[IsReadOnly]`, which "follows the MCP tool conventions"
 * in ArchitectureTest checks; its tools still authorize, against abilities that accept a null user.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ForGuests {}
