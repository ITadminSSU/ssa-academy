interface Props {
   from?: 'api' | 'web';
   item: 'exam' | 'course';
   item_id: number | string;
   children: React.ReactNode;
}

const CheckoutItem = ({ from = 'web', item, item_id, children }: Props) => {
   return <a href={route('payments.index', { from, item, id: item_id })}>{children}</a>;
};

export default CheckoutItem;
