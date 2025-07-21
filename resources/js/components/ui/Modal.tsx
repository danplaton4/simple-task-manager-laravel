import * as React from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose
} from './dialog';
import { cn } from '@/lib/utils';

interface ModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title?: React.ReactNode;
  description?: React.ReactNode;
  children: React.ReactNode;
  footer?: React.ReactNode;
  showCloseButton?: boolean;
  className?: string;
}

const Modal: React.FC<ModalProps> = ({
  open,
  onOpenChange,
  title,
  description,
  children,
  footer,
  showCloseButton = true,
  className
}) => (
  <Dialog open={open} onOpenChange={onOpenChange}>
    <DialogContent
      showCloseButton={showCloseButton}
      className={cn(
        'bg-card text-card-foreground p-0 overflow-hidden',
        className
      )}
      aria-describedby={description ? undefined : undefined}
    >
      <div className="flex flex-col">
        <DialogHeader className="px-6 pt-6">
          {title && <DialogTitle>{title}</DialogTitle>}
          <DialogDescription>{description || ''}</DialogDescription>
        </DialogHeader>
        <div className="px-6 pb-6 pt-2">{children}</div>
        {footer && <DialogFooter className="px-6 pb-6">{footer}</DialogFooter>}
      </div>
    </DialogContent>
  </Dialog>
);

export default Modal; 