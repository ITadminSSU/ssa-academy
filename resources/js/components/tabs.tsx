import { TabsProps } from '@radix-ui/react-tabs';
import React from 'react';
import { Tabs as TabsContainer } from './ui/tabs';

interface Props extends TabsProps {
   children: React.ReactNode;
}

const Tabs = ({ children, ...props }: Props) => {
   return <TabsContainer {...props}>{children}</TabsContainer>;
};

export default Tabs;
