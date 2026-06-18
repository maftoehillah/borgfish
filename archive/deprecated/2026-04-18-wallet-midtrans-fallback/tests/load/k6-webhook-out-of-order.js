import http from 'k6/http';
import { check, sleep } from 'k6';
import crypto from 'k6/crypto';
import { Rate } from 'k6/metrics';

const webhookAccepted = new Rate('webhook_accepted');

export const options = {
  scenarios: {
    out_of_order_webhook: {
      executor: 'constant-vus',
      vus: 10,
      duration: '2m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<1000'],
    webhook_accepted: ['rate>0.98'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const ORDER_IDS = (__ENV.ORDER_IDS || '').split(',').map((v) => v.trim()).filter(Boolean);
const SERVER_KEY = __ENV.SERVER_KEY || '';
const GROSS_AMOUNT = __ENV.GROSS_AMOUNT || '150000.00';
const PAYMENT_TYPE = __ENV.PAYMENT_TYPE || 'bank_transfer';

const EVENT_SEQUENCE = (__ENV.EVENT_SEQUENCE || 'pending,settlement,pending,expire')
  .split(',')
  .map((v) => v.trim())
  .filter(Boolean);

if (ORDER_IDS.length === 0) {
  throw new Error('ORDER_IDS wajib diisi, contoh: -e ORDER_IDS=BORGFISH-ORDER-1,BORGFISH-ORDER-2');
}

if (!SERVER_KEY) {
  throw new Error('SERVER_KEY wajib diisi agar signature webhook valid.');
}

if (EVENT_SEQUENCE.length === 0) {
  throw new Error('EVENT_SEQUENCE tidak boleh kosong.');
}

function resolveStatusCode(eventName) {
  switch (eventName) {
    case 'settlement':
    case 'capture':
      return '200';
    case 'pending':
      return '201';
    case 'expire':
      return '407';
    case 'deny':
    case 'cancel':
      return '202';
    default:
      return '200';
  }
}

function sign(orderId, statusCode, grossAmount, serverKey) {
  return crypto.sha512(`${orderId}${statusCode}${grossAmount}${serverKey}`, 'hex');
}

function pickOrderId() {
  const index = (__VU + __ITER) % ORDER_IDS.length;
  return ORDER_IDS[index];
}

function pickEventName() {
  const index = __ITER % EVENT_SEQUENCE.length;
  return EVENT_SEQUENCE[index];
}

export default function () {
  const orderId = pickOrderId();
  const transactionStatus = pickEventName();
  const statusCode = resolveStatusCode(transactionStatus);

  const payload = {
    order_id: orderId,
    transaction_status: transactionStatus,
    status_code: statusCode,
    gross_amount: GROSS_AMOUNT,
    payment_type: PAYMENT_TYPE,
    signature_key: sign(orderId, statusCode, GROSS_AMOUNT, SERVER_KEY),
  };

  const res = http.post(`${BASE_URL}/midtrans/webhook`, JSON.stringify(payload), {
    headers: {
      'Content-Type': 'application/json',
    },
  });

  const accepted = res.status === 200 || res.status === 422;
  webhookAccepted.add(accepted);

  check(res, {
    'webhook returns expected status': () => accepted,
  });

  sleep(0.2);
}
