export interface TodoItem {
    id: number;
    todo_list_id: number;
    title: string;
    is_done: boolean;
    due_date: string | null;
    start_date: string | null;
    end_date: string | null;
    order_index: number;
    completed_at_utc: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface TodoList {
    id: number;
    title: string;
    start_date: string | null;
    end_date: string | null;
    items: TodoItem[];
    created_at: string | null;
    updated_at: string | null;
}

export interface TodoListFormData {
    title: string;
    start_date?: string | null;
    end_date?: string | null;
}

export interface TodoItemFormData {
    title: string;
    due_date?: string | null;
    start_date?: string | null;
    end_date?: string | null;
    is_done?: boolean;
}
