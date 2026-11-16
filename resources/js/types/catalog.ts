/** One row of the admin category list (see Admin\CategoryController@index). */
export type AdminCategory = {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    products_count: number;
};
