import React from 'react';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    helperText?: string;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
    ({ label, error, helperText, className = '', id, ...props }, ref) => {
        const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

        return (
            <div className="w-full">
                {label && (
                    <label htmlFor={inputId} className="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                        {label}
                    </label>
                )}
                <input
                    ref={ref}
                    id={inputId}
                    className={`w-full px-3.5 py-2 text-sm bg-white dark:bg-gray-950 border rounded-lg transition-colors focus:outline-none focus:ring-2 disabled:bg-gray-100 disabled:cursor-not-allowed ${
                        error
                            ? 'border-red-500 focus:ring-red-400 text-red-900 dark:text-red-300'
                            : 'border-gray-300 dark:border-gray-700 focus:ring-blue-500 text-gray-900 dark:text-gray-100'
                    } ${className}`}
                    {...props}
                />
                {error && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>}
                {!error && helperText && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{helperText}</p>}
            </div>
        );
    }
);

Input.displayName = 'Input';
