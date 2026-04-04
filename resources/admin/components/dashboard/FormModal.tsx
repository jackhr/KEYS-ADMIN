import type { FormEventHandler, ReactNode } from "react";

import { Button } from "../ui/button";
import {
  Modal,
  ModalClose,
  ModalContent,
  ModalDescription,
  ModalFooter,
  ModalHeader,
  ModalTitle
} from "../ui/modal";

type FormModalProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: string;
  children: ReactNode;
  onSubmit: FormEventHandler<HTMLFormElement>;
  submitLabel: string;
  loading?: boolean;
  cancelLabel?: string;
  dangerActionLabel?: string;
  onDangerAction?: () => void;
  dangerActionDisabled?: boolean;
};

export default function FormModal({
  open,
  onOpenChange,
  title,
  description,
  children,
  onSubmit,
  submitLabel,
  loading = false,
  cancelLabel = "Cancel",
  dangerActionLabel,
  onDangerAction,
  dangerActionDisabled = false
}: FormModalProps) {
  return (
    <Modal open={open} onOpenChange={onOpenChange}>
      <ModalContent className="max-h-[90vh] overflow-hidden p-0">
        <ModalHeader className="shrink-0 border-b px-6 py-4 pr-12">
          <ModalTitle>{title}</ModalTitle>
          {description ? <ModalDescription>{description}</ModalDescription> : null}
        </ModalHeader>
        <form className="flex min-h-0 flex-1 flex-col" onSubmit={onSubmit}>
          <div className="flex-1 overflow-y-auto px-6 py-4">
            <div className="space-y-4">{children}</div>
          </div>
          <ModalFooter className="shrink-0 border-t bg-card px-6 py-4">
            {onDangerAction && dangerActionLabel ? (
              <Button
                type="button"
                variant="destructive"
                className="sm:mr-auto"
                onClick={onDangerAction}
                disabled={loading || dangerActionDisabled}
              >
                {dangerActionLabel}
              </Button>
            ) : null}
            <ModalClose asChild>
              <Button type="button" variant="outline" disabled={loading}>
                {cancelLabel}
              </Button>
            </ModalClose>
            <Button type="submit" disabled={loading}>
              {submitLabel}
            </Button>
          </ModalFooter>
        </form>
      </ModalContent>
    </Modal>
  );
}
