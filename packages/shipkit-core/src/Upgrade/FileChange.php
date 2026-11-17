<?php

namespace ShipKit\Core\Upgrade;

/**
 * What a template upgrade does to one file of the project.
 */
enum FileChange: string
{
    /** The project never edited it, so it takes the template's new version. */
    case Updates = 'updates';

    /** Both changed it, in different places, so the template's change merges in. */
    case MergesCleanly = 'merges cleanly';

    /** Both changed the same lines, or the project edited a file the template removed or added. */
    case Conflicts = 'conflicts';

    /** The template added it. */
    case New = 'new';

    /** The template removed it, and the project never edited it. */
    case Removed = 'removed';

    /** The template changed it, but the project deleted it; it stays deleted. */
    case DeletedInProject = 'deleted in your project';

    /** The project already has the template's new version. */
    case AlreadyApplied = 'already applied';
}
