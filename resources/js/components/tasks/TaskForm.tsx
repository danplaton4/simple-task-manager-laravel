import React, { useEffect, useState, useCallback, useMemo } from "react";
import { useForm } from "react-hook-form";
import { Task, TaskFormData, Translations, Language } from "@/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
    FormDescription,
} from "@/components/ui/form";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Calendar,
    Clock,
    Flag,
    User,
    AlertCircle,
    Save,
    X,
    CheckCircle,
    AlertTriangle,
    Globe,
} from "lucide-react";
import { useLanguage } from "@/contexts/LanguageContext";

interface TaskFormProps {
    task?: Task;
    onSubmit: (taskData: any) => void;
    onCancel: () => void;
    availableParents?: Task[];
    loading?: boolean;
    showCard?: boolean;
}

const TaskForm: React.FC<TaskFormProps> = ({
    task,
    onSubmit,
    onCancel,
    availableParents = [],
    loading = false,
    showCard = false,
}) => {
    const { language } = useLanguage();
    const [activeLang, setActiveLang] = useState<Language>(language);
    const [unsavedChanges, setUnsavedChanges] = useState<Record<string, boolean>>({});
    const [hasInteracted, setHasInteracted] = useState(false);
    const supportedLanguages: Language[] = ["en", "fr", "de"];

    const form = useForm<TaskFormData>({
        defaultValues: {
            name: { en: "", fr: "", de: "" },
            description: { en: "", fr: "", de: "" },
            status: task?.status || "pending",
            priority: task?.priority || "medium",
            due_date: task?.due_date ? task.due_date.split("T")[0] : "",
            parent_id: task?.parent_id || undefined,
        },
        mode: "onChange", // Enable real-time validation
    });

    // Calculate translation completeness
    const translationCompleteness = useMemo(() => {
        const formValues = form.watch();
        const completeness: Record<Language, { name: boolean; description: boolean; complete: boolean; percentage: number }> = {} as any;
        
        supportedLanguages.forEach(lang => {
            const hasName = Boolean(formValues.name?.[lang]?.trim());
            const hasDescription = Boolean(formValues.description?.[lang]?.trim());
            const complete = hasName; // Name is required, description is optional
            const percentage = hasName ? (hasDescription ? 100 : 75) : 0;
            
            completeness[lang] = {
                name: hasName,
                description: hasDescription,
                complete,
                percentage
            };
        });
        
        return completeness;
    }, [form.watch(), supportedLanguages]);

    // Track unsaved changes for each language
    const trackUnsavedChanges = useCallback((lang: Language, hasChanges: boolean) => {
        setUnsavedChanges(prev => ({
            ...prev,
            [lang]: hasChanges
        }));
    }, []);

    // Check if current language has unsaved changes
    const hasUnsavedChangesInCurrentLang = useMemo(() => {
        return unsavedChanges[activeLang] || false;
    }, [unsavedChanges, activeLang]);

    // Reset form when task changes
    useEffect(() => {
        if (task) {
            // Prefer translations from BE if present
            const nameTranslations =
                (task as any).translations?.name ||
                (typeof task.name === "object"
                    ? task.name
                    : { en: task.name, fr: "", de: "" });
            const descriptionTranslations =
                (task as any).translations?.description ||
                (typeof task.description === "object"
                    ? task.description
                    : { en: task.description || "", fr: "", de: "" });
            form.reset({
                name: nameTranslations,
                description: descriptionTranslations,
                status: task.status,
                priority: task.priority,
                due_date: task.due_date ? task.due_date.split("T")[0] : "",
                parent_id: task.parent_id || undefined,
            });
            // Reset unsaved changes tracking
            setUnsavedChanges({});
            setHasInteracted(false);
        }
    }, [task, form]);

    // Track form changes to detect unsaved changes
    useEffect(() => {
        const subscription = form.watch((value, { name }) => {
            if (name && hasInteracted) {
                // Extract language from field name (e.g., "name.en" -> "en")
                const fieldParts = name.split('.');
                if (fieldParts.length === 2 && supportedLanguages.includes(fieldParts[1] as Language)) {
                    const lang = fieldParts[1] as Language;
                    trackUnsavedChanges(lang, true);
                }
            }
        });
        return () => subscription.unsubscribe();
    }, [form, hasInteracted, trackUnsavedChanges, supportedLanguages]);

    // Set active language to current user language on mount
    useEffect(() => {
        setActiveLang(language);
    }, [language]);

    const handleSubmit = (data: TaskFormData) => {
        // Always send all supported translations for name and description
        const supportedLanguages = ["en", "fr", "de"];
        let mergedName: Record<string, string> = {};
        let mergedDescription: Record<string, string> = {};
        // Merge with existing translations if editing
        if (task) {
            const safeName = typeof task.name === "object" ? task.name : { en: task.name || "" };
            const safeDesc = typeof task.description === "object" ? task.description : { en: task.description || "" };
            supportedLanguages.forEach(lang => {
                mergedName[lang] = data.name?.[lang] ?? safeName[lang] ?? "";
                mergedDescription[lang] = data.description?.[lang] ?? safeDesc[lang] ?? "";
            });
        } else {
            supportedLanguages.forEach(lang => {
                mergedName[lang] = data.name?.[lang] ?? "";
                mergedDescription[lang] = data.description?.[lang] ?? "";
            });
        }
        
        // Clear unsaved changes tracking on successful submit
        setUnsavedChanges({});
        setHasInteracted(false);
        
        // Submit with all translations
        onSubmit({
            ...data,
            name: mergedName,
            description: mergedDescription,
        });
    };

    const getTranslation = (
        field: string | Translations | undefined,
        lang: string = "en"
    ) => {
        if (typeof field === "object" && field !== null && field[lang]) {
            return field[lang];
        }
        return typeof field === "string" ? field : "";
    };

    const validateDueDate = (value: string | undefined) => {
        if (!value) return true;
        const selectedDate = new Date(value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (selectedDate < today) {
            return "Due date cannot be in the past";
        }
        return true;
    };

    const getStatusDescription = (status: Task["status"]) => {
        switch (status) {
            case "pending":
                return "Task is waiting to be started";
            case "in_progress":
                return "Task is currently being worked on";
            case "completed":
                return "Task has been finished";
            case "cancelled":
                return "Task has been cancelled and will not be completed";
            default:
                return "";
        }
    };

    const getPriorityDescription = (priority: Task["priority"]) => {
        switch (priority) {
            case "low":
                return "Can be done when time permits";
            case "medium":
                return "Normal priority task";
            case "high":
                return "Should be completed soon";
            case "urgent":
                return "Requires immediate attention";
            default:
                return "";
        }
    };

    // Translation status indicator component
    const TranslationStatusIndicator: React.FC<{ lang: Language }> = ({ lang }) => {
        const status = translationCompleteness[lang];
        const isActive = activeLang === lang;
        const hasUnsaved = unsavedChanges[lang];
        
        if (!status) return null;

        const getStatusIcon = () => {
            if (hasUnsaved) {
                return <AlertTriangle className="h-3 w-3 text-amber-500" />;
            }
            if (status.complete) {
                return <CheckCircle className="h-3 w-3 text-green-500" />;
            }
            return <AlertCircle className="h-3 w-3 text-red-500" />;
        };

        const getStatusColor = () => {
            if (hasUnsaved) return "border-amber-500";
            if (status.complete) return "border-green-500";
            return "border-red-500";
        };

        return (
            <div className="flex items-center gap-1">
                {getStatusIcon()}
                <span className="text-xs text-muted-foreground">
                    {status.percentage}%
                </span>
                {hasUnsaved && (
                    <span className="text-xs text-amber-600">•</span>
                )}
            </div>
        );
    };

    // Handle language tab switching with unsaved changes warning
    const handleLanguageSwitch = useCallback((newLang: Language) => {
        if (hasUnsavedChangesInCurrentLang && hasInteracted) {
            const confirmSwitch = window.confirm(
                `You have unsaved changes in ${activeLang.toUpperCase()}. Are you sure you want to switch to ${newLang.toUpperCase()}?`
            );
            if (!confirmSwitch) {
                return;
            }
        }
        setActiveLang(newLang);
        setHasInteracted(true);
    }, [activeLang, hasUnsavedChangesInCurrentLang, hasInteracted]);

    // Enhanced validation rules
    const getValidationRules = (field: 'name' | 'description', lang: Language) => {
        const isRequired = field === 'name' && lang === 'en';
        const rules: any = {};

        if (isRequired) {
            rules.required = `${field === 'name' ? 'Task name' : 'Description'} in English is required`;
        }

        if (field === 'name') {
            rules.minLength = {
                value: 3,
                message: `Task name in ${lang.toUpperCase()} must be at least 3 characters`
            };
            rules.maxLength = {
                value: 255,
                message: `Task name in ${lang.toUpperCase()} cannot exceed 255 characters`
            };
        } else if (field === 'description') {
            rules.maxLength = {
                value: 1000,
                message: `Description in ${lang.toUpperCase()} cannot exceed 1000 characters`
            };
        }

        return rules;
    };

    const formContent = (
        <Form {...form}>
            <form
                onSubmit={form.handleSubmit(handleSubmit)}
                className="space-y-6"
            >
                {/* Language Tabs with Translation Status */}
                <div className="border-b">
                    <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center gap-2">
                            <Globe className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm font-medium text-muted-foreground">
                                Translation Status
                            </span>
                        </div>
                        {hasInteracted && Object.values(unsavedChanges).some(Boolean) && (
                            <div className="flex items-center gap-1 text-xs text-amber-600">
                                <AlertTriangle className="h-3 w-3" />
                                Unsaved changes
                            </div>
                        )}
                    </div>
                    <div className="flex space-x-4">
                        {supportedLanguages.map((lang) => (
                            <button
                                key={lang}
                                type="button"
                                onClick={() => handleLanguageSwitch(lang)}
                                className={`py-2 px-4 text-sm font-medium flex items-center gap-2 transition-colors ${
                                    activeLang === lang
                                        ? "border-b-2 border-primary text-primary"
                                        : "text-muted-foreground hover:text-foreground"
                                }`}
                            >
                                <span>{lang.toUpperCase()}</span>
                                <TranslationStatusIndicator lang={lang} />
                            </button>
                        ))}
                    </div>
                </div>

                {supportedLanguages.map((lang) => (
                    <div
                        key={lang}
                        style={{
                            display: activeLang === lang ? "block" : "none",
                        }}
                    >
                        {/* Task Name */}
                        <FormField
                            control={form.control}
                            name={
                                `name.${lang}` as
                                    | "name.en"
                                    | "name.fr"
                                    | "name.de"
                            }
                            rules={getValidationRules('name', lang)}
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel className="flex items-center gap-2">
                                        Task Name ({lang.toUpperCase()})
                                        {lang === 'en' && (
                                            <span className="text-red-500 text-xs">*</span>
                                        )}
                                        {translationCompleteness[lang]?.name && (
                                            <CheckCircle className="h-3 w-3 text-green-500" />
                                        )}
                                    </FormLabel>
                                    <FormControl>
                                        <Input
                                            placeholder={`Enter task name in ${lang.toUpperCase()}${lang === 'en' ? ' (required)' : ' (optional)'}`}
                                            {...field}
                                            onChange={(e) => {
                                                field.onChange(e);
                                                setHasInteracted(true);
                                            }}
                                        />
                                    </FormControl>
                                    <FormDescription>
                                        {lang === 'en' 
                                            ? 'English name is required for all tasks'
                                            : `Optional translation in ${lang.toUpperCase()}`
                                        }
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        {/* Description */}
                        <FormField
                            control={form.control}
                            name={
                                `description.${lang}` as
                                    | "description.en"
                                    | "description.fr"
                                    | "description.de"
                            }
                            rules={getValidationRules('description', lang)}
                            render={({ field }) => (
                                <FormItem className="mt-6">
                                    <FormLabel className="flex items-center gap-2">
                                        Description ({lang.toUpperCase()})
                                        {translationCompleteness[lang]?.description && (
                                            <CheckCircle className="h-3 w-3 text-green-500" />
                                        )}
                                    </FormLabel>
                                    <FormControl>
                                        <Textarea
                                            placeholder={`Enter description in ${lang.toUpperCase()} (optional)`}
                                            {...field}
                                            onChange={(e) => {
                                                field.onChange(e);
                                                setHasInteracted(true);
                                            }}
                                        />
                                    </FormControl>
                                    <FormDescription>
                                        Optional detailed description in {lang.toUpperCase()}
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </div>
                ))}

                {/* Status and Priority Row */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t">
                    <FormField
                        control={form.control}
                        name="status"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel className="flex items-center gap-2">
                                    <Clock className="h-4 w-4" />
                                    Status
                                </FormLabel>
                                <Select
                                    onValueChange={field.onChange}
                                    value={field.value}
                                >
                                    <FormControl>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        <SelectItem value="pending">
                                            <div className="flex items-center gap-2">
                                                <div className="w-2 h-2 rounded-full bg-gray-400"></div>
                                                Pending
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="in_progress">
                                            <div className="flex items-center gap-2">
                                                <div className="w-2 h-2 rounded-full bg-blue-500"></div>
                                                In Progress
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="completed">
                                            <div className="flex items-center gap-2">
                                                <div className="w-2 h-2 rounded-full bg-green-500"></div>
                                                Completed
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="cancelled">
                                            <div className="flex items-center gap-2">
                                                <div className="w-2 h-2 rounded-full bg-red-500"></div>
                                                Cancelled
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FormDescription>
                                    {getStatusDescription(form.watch("status"))}
                                </FormDescription>
                                <FormMessage />
                            </FormItem>
                        )}
                    />

                    <FormField
                        control={form.control}
                        name="priority"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel className="flex items-center gap-2">
                                    <Flag className="h-4 w-4" />
                                    Priority
                                </FormLabel>
                                <Select
                                    onValueChange={field.onChange}
                                    value={field.value}
                                >
                                    <FormControl>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select priority" />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        <SelectItem value="low">
                                            <div className="flex items-center gap-2">
                                                <Flag className="h-3 w-3 text-gray-400" />
                                                Low
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="medium">
                                            <div className="flex items-center gap-2">
                                                <Flag className="h-3 w-3 text-yellow-500" />
                                                Medium
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="high">
                                            <div className="flex items-center gap-2">
                                                <Flag className="h-3 w-3 text-orange-500" />
                                                High
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="urgent">
                                            <div className="flex items-center gap-2">
                                                <Flag className="h-3 w-3 text-red-500" />
                                                Urgent
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FormDescription>
                                    {getPriorityDescription(
                                        form.watch("priority")
                                    )}
                                </FormDescription>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                </div>

                {/* Due Date and Parent Task Row */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <FormField
                        control={form.control}
                        name="due_date"
                        rules={{
                            validate: validateDueDate,
                        }}
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel className="flex items-center gap-2">
                                    <Calendar className="h-4 w-4" />
                                    Due Date
                                </FormLabel>
                                <FormControl>
                                    <Input
                                        type="date"
                                        {...field}
                                        min={
                                            new Date()
                                                .toISOString()
                                                .split("T")[0]
                                        }
                                    />
                                </FormControl>
                                <FormDescription>
                                    When should this task be completed?
                                    (optional)
                                </FormDescription>
                                <FormMessage />
                            </FormItem>
                        )}
                    />

                    {availableParents.length > 0 && (
                        <FormField
                            control={form.control}
                            name="parent_id"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Parent Task</FormLabel>
                                    <Select
                                        onValueChange={(value) =>
                                            field.onChange(
                                                value
                                                    ? parseInt(value)
                                                    : undefined
                                            )
                                        }
                                        value={field.value?.toString() || ""}
                                    >
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select parent task (optional)" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            {availableParents.map((parent) => (
                                                <SelectItem
                                                    key={parent.id}
                                                    value={parent.id.toString()}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <div
                                                            className={`w-2 h-2 rounded-full ${
                                                                parent.status ===
                                                                "completed"
                                                                    ? "bg-green-500"
                                                                    : parent.status ===
                                                                      "in_progress"
                                                                    ? "bg-blue-500"
                                                                    : "bg-gray-400"
                                                            }`}
                                                        ></div>
                                                        {getTranslation(
                                                            parent.name
                                                        )}
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FormDescription>
                                        Make this task a subtask of another task
                                        (optional)
                                    </FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    )}
                </div>

                {/* Form Actions */}
                <div className="flex flex-col sm:flex-row justify-between items-center gap-3 pt-6 border-t border-gray-200">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        {hasInteracted && (
                            <>
                                <span>English translation:</span>
                                {translationCompleteness.en?.complete ? (
                                    <span className="flex items-center gap-1 text-green-600">
                                        <CheckCircle className="h-3 w-3" />
                                        Complete
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-1 text-red-600">
                                        <AlertCircle className="h-3 w-3" />
                                        Required
                                    </span>
                                )}
                            </>
                        )}
                    </div>
                    <div className="flex gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                            disabled={loading}
                            className="flex items-center gap-2"
                        >
                            <X className="h-4 w-4" />
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={loading || !form.formState.isValid || !translationCompleteness.en?.complete}
                            className="flex items-center gap-2"
                        >
                            <Save className="h-4 w-4" />
                            {loading
                                ? "Saving..."
                                : task
                                ? "Update Task"
                                : "Create Task"}
                        </Button>
                    </div>
                </div>

                {/* Enhanced Validation Summary */}
                {Object.keys(form.formState.errors).length > 0 && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div className="flex items-center gap-2 text-red-800 mb-3">
                            <AlertCircle className="h-4 w-4" />
                            <span className="font-medium">
                                Please fix the following errors:
                            </span>
                        </div>
                        <div className="space-y-3">
                            {/* Translation-specific errors */}
                            {supportedLanguages.map(lang => {
                                const nameError = form.formState.errors.name?.[lang];
                                const descError = form.formState.errors.description?.[lang];
                                
                                if (!nameError && !descError) return null;
                                
                                return (
                                    <div key={lang} className="border-l-2 border-red-300 pl-3">
                                        <div className="font-medium text-red-800 text-sm mb-1">
                                            {lang.toUpperCase()} Translation:
                                        </div>
                                        <ul className="text-sm text-red-700 space-y-1">
                                            {nameError && (
                                                <li>• Name: {nameError.message}</li>
                                            )}
                                            {descError && (
                                                <li>• Description: {descError.message}</li>
                                            )}
                                        </ul>
                                    </div>
                                );
                            })}
                            
                            {/* Other field errors */}
                            {Object.entries(form.formState.errors)
                                .filter(([field]) => !['name', 'description'].includes(field))
                                .map(([field, error]) => {
                                    let message = "An error occurred";
                                    if (error && typeof error === "object" && "message" in error) {
                                        message = error.message as string;
                                    }
                                    return (
                                        <div key={field} className="text-sm text-red-700">
                                            • {field.charAt(0).toUpperCase() + field.slice(1)}: {message}
                                        </div>
                                    );
                                })}
                        </div>
                    </div>
                )}

                {/* Translation Completeness Summary */}
                {hasInteracted && (
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div className="flex items-center gap-2 text-blue-800 mb-2">
                            <Globe className="h-4 w-4" />
                            <span className="font-medium">Translation Status</span>
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            {supportedLanguages.map(lang => {
                                const status = translationCompleteness[lang];
                                return (
                                    <div key={lang} className="text-center">
                                        <div className="text-sm font-medium text-blue-800">
                                            {lang.toUpperCase()}
                                        </div>
                                        <div className="flex items-center justify-center gap-1 mt-1">
                                            {status.complete ? (
                                                <CheckCircle className="h-4 w-4 text-green-500" />
                                            ) : (
                                                <AlertCircle className="h-4 w-4 text-red-500" />
                                            )}
                                            <span className="text-xs text-blue-700">
                                                {status.percentage}%
                                            </span>
                                        </div>
                                        {unsavedChanges[lang] && (
                                            <div className="text-xs text-amber-600 mt-1">
                                                Unsaved changes
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </form>
        </Form>
    );

    if (showCard) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>
                        {task ? "Edit Task" : "Create New Task"}
                    </CardTitle>
                </CardHeader>
                <CardContent>{formContent}</CardContent>
            </Card>
        );
    }

    return formContent;
};

export default TaskForm;
