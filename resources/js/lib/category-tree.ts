import type { AdminCategory } from '@/types/catalog';

export type CategoryNode = AdminCategory & {
    /** 1 = root, 2 = child, 3 = grandchild. */
    level: number;
    childCount: number;
};

/**
 * Turns the flat list from the server into display order:
 * every parent followed directly by its children (depth-first).
 * The input is already sorted by sort_order, then name.
 */
export function flattenTree(categories: AdminCategory[]): CategoryNode[] {
    const childrenOf = new Map<number | null, AdminCategory[]>();

    for (const category of categories) {
        const siblings = childrenOf.get(category.parent_id) ?? [];
        siblings.push(category);
        childrenOf.set(category.parent_id, siblings);
    }

    const result: CategoryNode[] = [];

    const visit = (parentId: number | null, level: number) => {
        for (const category of childrenOf.get(parentId) ?? []) {
            result.push({
                ...category,
                level,
                childCount: childrenOf.get(category.id)?.length ?? 0,
            });
            visit(category.id, level + 1);
        }
    };

    visit(null, 1);

    return result;
}

/** IDs of every category below `id` (children, grandchildren, ...). */
function descendantIds(nodes: CategoryNode[], id: number): Set<number> {
    const ids = new Set<number>();
    let frontier = [id];

    while (frontier.length > 0) {
        frontier = nodes
            .filter(
                (n) => n.parent_id !== null && frontier.includes(n.parent_id),
            )
            .map((n) => n.id);
        frontier.forEach((childId) => ids.add(childId));
    }

    return ids;
}

/**
 * Categories that may become the parent of `editing` (or of a new category
 * when `editing` is null). Mirrors the server rules in CategoryRequest so the
 * dropdown only offers valid choices; the server still checks everything.
 */
export function allowedParents(
    nodes: CategoryNode[],
    editing: CategoryNode | null,
    maxDepth: number,
): CategoryNode[] {
    if (editing === null) {
        return nodes.filter((n) => n.level < maxDepth);
    }

    const below = descendantIds(nodes, editing.id);
    const levelsBelow =
        below.size === 0
            ? 0
            : Math.max(
                  ...nodes.filter((n) => below.has(n.id)).map((n) => n.level),
              ) - editing.level;

    return nodes.filter(
        (n) =>
            n.id !== editing.id &&
            !below.has(n.id) &&
            n.level + 1 + levelsBelow <= maxDepth,
    );
}
