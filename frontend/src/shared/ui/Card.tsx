import React from 'react';

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
    padding?: 'none' | 'sm' | 'md' | 'lg';
}

export const Card: React.FC<CardProps> = ({
    children,
    padding = 'md',
    className = '',
    ...props
}) => {
    const paddingStyles = {
        none: '',
        sm: 'p-3',
        md: 'p-5',
        lg: 'p-6',
    }[padding];

    return (
        <div
            className={`bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm ${paddingStyles} ${className}`}
            {...props}
        >
            {children}
        </div>
    );
};

export const CardHeader: React.FC<React.HTMLAttributes<HTMLDivElement>> = ({ children, className = '', ...props }) => (
    <div className={`mb-4 flex flex-col space-y-1.5 ${className}`} {...props}>
        {children}
    </div>
);

export const CardTitle: React.FC<React.HTMLAttributes<HTMLHeadingElement>> = ({ children, className = '', ...props }) => (
    <h3 className={`text-lg font-semibold text-gray-900 dark:text-gray-100 leading-none tracking-tight ${className}`} {...props}>
        {children}
    </h3>
);

export const CardContent: React.FC<React.HTMLAttributes<HTMLDivElement>> = ({ children, className = '', ...props }) => (
    <div className={`text-sm text-gray-600 dark:text-gray-400 ${className}`} {...props}>
        {children}
    </div>
);
