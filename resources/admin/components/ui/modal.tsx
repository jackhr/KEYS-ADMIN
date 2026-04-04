import type { ComponentProps } from "react";

import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger
} from "./dialog";

function Modal(props: ComponentProps<typeof Dialog>) {
  return <Dialog {...props} />;
}

function ModalTrigger(props: ComponentProps<typeof DialogTrigger>) {
  return <DialogTrigger {...props} />;
}

function ModalClose(props: ComponentProps<typeof DialogClose>) {
  return <DialogClose {...props} />;
}

function ModalContent(props: ComponentProps<typeof DialogContent>) {
  return <DialogContent {...props} />;
}

function ModalHeader(props: ComponentProps<typeof DialogHeader>) {
  return <DialogHeader {...props} />;
}

function ModalFooter(props: ComponentProps<typeof DialogFooter>) {
  return <DialogFooter {...props} />;
}

function ModalTitle(props: ComponentProps<typeof DialogTitle>) {
  return <DialogTitle {...props} />;
}

function ModalDescription(props: ComponentProps<typeof DialogDescription>) {
  return <DialogDescription {...props} />;
}

export {
  Modal,
  ModalTrigger,
  ModalClose,
  ModalContent,
  ModalHeader,
  ModalFooter,
  ModalTitle,
  ModalDescription
};
