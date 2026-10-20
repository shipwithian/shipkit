import { usePage } from '@inertiajs/react';
import type { AuthUser } from '@/types';

/**
 * The signed-in user, for pages behind the `auth` middleware. `auth.user` is
 * null for guests, so a page a guest can reach reads `usePage().props.auth`
 * and handles the null itself.
 */
export function useAuthUser(): AuthUser {
    const { user } = usePage().props.auth;

    if (user === null) {
        throw new Error('This page requires a signed-in user.');
    }

    return user;
}
