import React from 'react';
import { createRoot } from 'react-dom/client';
import './bootstrap';
import '../css/app.css';

function App() {
    return (
        <div className="min-h-screen bg-gray-100">
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold text-gray-900 mb-8">
                    Task Management App
                </h1>
                <div className="bg-white rounded-lg shadow-md p-6">
                    <p className="text-gray-600">
                        Welcome to the Task Management Application. 
                        The foundation has been set up successfully!
                    </p>
                </div>
            </div>
        </div>
    );
}

const container = document.getElementById('app');
if (container) {
    const root = createRoot(container);
    root.render(<App />);
}