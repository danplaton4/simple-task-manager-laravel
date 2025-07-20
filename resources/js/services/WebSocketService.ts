import { Task } from '@/types';

export interface TaskUpdateEvent {
  task_id: number;
  action: 'created' | 'updated' | 'deleted' | 'restored';
  task_data?: Task;
  timestamp: string;
}

export type TaskUpdateCallback = (event: TaskUpdateEvent) => void;

class WebSocketService {
  private ws: WebSocket | null = null;
  private callbacks: Set<TaskUpdateCallback> = new Set();
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 5;
  private reconnectDelay = 1000;
  private userId: number | null = null;

  connect(userId: number) {
    this.userId = userId;
    this.connectWebSocket();
  }

  private connectWebSocket() {
    if (!this.userId) return;

    try {
      // In a real implementation, this would connect to your WebSocket server
      // For now, we'll simulate with a mock connection
      console.log(`Connecting to WebSocket for user ${this.userId}`);
      
      // Simulate connection success
      setTimeout(() => {
        this.onOpen();
      }, 100);

      // Simulate periodic updates for demo purposes
      this.startMockUpdates();
      
    } catch (error) {
      console.error('WebSocket connection failed:', error);
      this.onError();
    }
  }

  private startMockUpdates() {
    // This is just for demonstration - in a real app, updates would come from the server
    setInterval(() => {
      if (Math.random() > 0.95) { // 5% chance every interval
        const mockEvent: TaskUpdateEvent = {
          task_id: Math.floor(Math.random() * 100),
          action: 'updated',
          timestamp: new Date().toISOString()
        };
        this.notifyCallbacks(mockEvent);
      }
    }, 5000);
  }

  private onOpen() {
    console.log('WebSocket connected');
    this.reconnectAttempts = 0;
  }

  private onMessage(event: MessageEvent) {
    try {
      const data: TaskUpdateEvent = JSON.parse(event.data);
      this.notifyCallbacks(data);
    } catch (error) {
      console.error('Failed to parse WebSocket message:', error);
    }
  }

  private onError() {
    console.error('WebSocket error occurred');
    this.reconnect();
  }

  private onClose() {
    console.log('WebSocket connection closed');
    this.reconnect();
  }

  private reconnect() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('Max reconnection attempts reached');
      return;
    }

    this.reconnectAttempts++;
    const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
    
    console.log(`Attempting to reconnect in ${delay}ms (attempt ${this.reconnectAttempts})`);
    
    setTimeout(() => {
      this.connectWebSocket();
    }, delay);
  }

  private notifyCallbacks(event: TaskUpdateEvent) {
    this.callbacks.forEach(callback => {
      try {
        callback(event);
      } catch (error) {
        console.error('Error in WebSocket callback:', error);
      }
    });
  }

  subscribe(callback: TaskUpdateCallback) {
    this.callbacks.add(callback);
    
    return () => {
      this.callbacks.delete(callback);
    };
  }

  disconnect() {
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    this.callbacks.clear();
    this.userId = null;
  }

  // Method to send task updates (for optimistic updates)
  sendTaskUpdate(taskId: number, action: TaskUpdateEvent['action'], taskData?: Task) {
    const event: TaskUpdateEvent = {
      task_id: taskId,
      action,
      task_data: taskData,
      timestamp: new Date().toISOString()
    };
    
    // In a real implementation, this would send to the server
    console.log('Sending task update:', event);
    
    // For demo purposes, echo back the update
    setTimeout(() => {
      this.notifyCallbacks(event);
    }, 100);
  }
}

export const webSocketService = new WebSocketService();