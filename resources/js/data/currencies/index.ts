import mollieCurrencies from './mollie';
import paypalCurrencies from './paypal';
import paystackCurrencies from './paystack';
import razorpayCurrencies from './razorpay';
import sslcommerzCurrency from './sslcommerz';
import stripeCurrencies from './stripe';

const currencies = Array.from(
   new Map(
      [...mollieCurrencies, ...paypalCurrencies, ...paystackCurrencies, ...razorpayCurrencies, ...sslcommerzCurrency, ...stripeCurrencies].map(
         (currency) => [`${currency.value}-${currency.symbol}`, currency],
      ),
   ).values(),
);

export default currencies;
