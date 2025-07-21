// Global type declarations for window object extensions

declare global {
  interface Window {
    axios: import('axios').AxiosStatic;
    updateAxiosLocaleHeaders: (locale: string) => void;
  }
}

export {};