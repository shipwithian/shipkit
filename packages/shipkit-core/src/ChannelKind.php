<?php

namespace ShipKit\Core;

/**
 * The kinds of client a request can come from.
 */
enum ChannelKind: string
{
    case Web = 'web';
    case Api = 'api';
    case Mcp = 'mcp';
}
