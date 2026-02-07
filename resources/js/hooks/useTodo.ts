import type { TodoItem, TodoItemFormData, TodoList, TodoListFormData } from '@/types/todo';
import axios from 'axios';
import { useCallback, useState } from 'react';

export function useTodo() {
    const [lists, setLists] = useState<TodoList[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchLists = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get('/todo-lists');
            setLists(response.data.lists);
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to fetch todo lists';
            setError(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const createList = useCallback(async (data: TodoListFormData) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.post('/todo-lists', data);
            const newList = response.data.list as TodoList;
            setLists((prev) => [newList, ...prev]);
            return newList;
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to create list';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const updateList = useCallback(async (listId: number, data: TodoListFormData) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.patch(`/todo-lists/${listId}`, data);
            const updatedList = response.data.list as TodoList;
            setLists((prev) => prev.map((l) => (l.id === listId ? updatedList : l)));
            return updatedList;
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to update list';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const deleteList = useCallback(async (listId: number) => {
        setLoading(true);
        setError(null);
        try {
            await axios.delete(`/todo-lists/${listId}`);
            setLists((prev) => prev.filter((l) => l.id !== listId));
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to delete list';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const createItem = useCallback(async (listId: number, data: TodoItemFormData) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.post(`/todo-items/${listId}`, data);
            const newItem = response.data.item as TodoItem;
            setLists((prev) =>
                prev.map((list) =>
                    list.id === listId
                        ? { ...list, items: [...list.items, newItem] }
                        : list,
                ),
            );
            return newItem;
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to create item';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const updateItem = useCallback(async (itemId: number, data: TodoItemFormData) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.patch(`/todo-items/${itemId}`, data);
            const updatedItem = response.data.item as TodoItem;
            setLists((prev) =>
                prev.map((list) => ({
                    ...list,
                    items: list.items.map((item) =>
                        item.id === itemId ? updatedItem : item,
                    ),
                })),
            );
            return updatedItem;
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to update item';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const toggleItem = useCallback(async (itemId: number, isDone: boolean) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.patch(`/todo-items/${itemId}/toggle`, {
                is_done: isDone,
            });
            const updatedItem = response.data.item as TodoItem;
            setLists((prev) =>
                prev.map((list) => ({
                    ...list,
                    items: list.items.map((item) =>
                        item.id === itemId ? updatedItem : item,
                    ),
                })),
            );
            return updatedItem;
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to toggle item';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const deleteItem = useCallback(async (itemId: number) => {
        setLoading(true);
        setError(null);
        try {
            await axios.delete(`/todo-items/${itemId}`);
            setLists((prev) =>
                prev.map((list) => ({
                    ...list,
                    items: list.items.filter((item) => item.id !== itemId),
                })),
            );
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to delete item';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const reorderItems = useCallback(async (listId: number, orderedIds: number[]) => {
        setLoading(true);
        setError(null);
        try {
            await axios.patch(`/todo-lists/${listId}/items/reorder`, {
                ordered_ids: orderedIds,
            });
        } catch (err: unknown) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to reorder items';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    return {
        lists,
        loading,
        error,
        fetchLists,
        createList,
        updateList,
        deleteList,
        createItem,
        updateItem,
        toggleItem,
        deleteItem,
        reorderItems,
    };
}
