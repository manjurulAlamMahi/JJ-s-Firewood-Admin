# Payment & Order API Documentation

## Base URL
```
https://your-domain.com/api
```

---

## Authentication
All endpoints require Bearer token authentication.

**Headers:**
```
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

---

## 1. Create Order & Process Payment

### Endpoint
```
POST /orders/newstore
```

### Description
Create a new order and optionally process payment directly.

### Request Body
```json
{
  "shipping_address_id": 17,
  "preferred_delivery_date": "17-01-2025",
  "notes": "Special delivery instructions",
  "source_id": "optional_square_token"
}
```

### Parameters
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `shipping_address_id` | integer | Yes | User's shipping address ID |
| `preferred_delivery_date` | string | No | Format: DD-MM-YYYY |
| `notes` | string | No | Order notes (max 1000 chars) |
| `source_id` | string | No | Square payment token for direct payment |

### Success Response (200)
```json
{
  "success": true,
  "message": "Order placed successfully.",
  "data": {
    "order": {
      "id": 55,
      "order_number": "JJS00000053",
      "user_id": 4,
      "shipping_address_id": 17,
      "subtotal": "1000.00",
      "delivery_fee": "0.00",
      "discount": "0.00",
      "tax": "0.00",
      "total": "1000.00",
      "payment_method": "square",
      "payment_status": "paid",
      "order_status": "processing",
      "preferred_delivery_date": null,
      "notes": null,
      "loyalty_points": 1000,
      "delivery_type_id": 1,
      "transaction_id": "ZaP6V9mU1B1ZXVmpNq4FUP4ypDdZY",
      "created_at": "2026-01-14T00:53:43.000000Z",
      "updated_at": "2026-01-14T00:53:43.000000Z",
      "order_items": [
        {
          "id": 55,
          "order_id": 55,
          "product_id": 2,
          "product_name": "Sample Product 2",
          "quantity": "1.00",
          "price": "1000.00",
          "subtotal": "1000.00",
          "product": {...}
        }
      ],
      "user": {...},
      "address": {...},
      "delivery_type": {...}
    },
    "payment_status": "paid",
    "code": 200
  }
}
```

### Error Response (400)
```json
{
  "success": false,
  "message": "Cart is empty."
}
```

---

## 2. Get Payment Success Details

### Endpoint
```
GET /payments/success/{orderId}
```

### Description
Retrieve complete payment and order details after successful payment.

### URL Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `orderId` | integer | Yes | The order ID from payment response |

### Success Response (200)
```json
{
  "success": true,
  "data": {
    "success": true,
    "message": "Payment successful! Your order has been placed.",
    "order": {
      "id": 55,
      "order_number": "JJS00000053",
      "user_id": 4,
      "shipping_address_id": 17,
      "coupon_id": null,
      "subtotal": "1000.00",
      "delivery_fee": "0.00",
      "discount": "0.00",
      "tax": "0.00",
      "total": "1000.00",
      "payment_method": "square",
      "payment_status": "paid",
      "preferred_delivery_date": null,
      "order_status": "processing",
      "notes": null,
      "loyalty_points": 1000,
      "delivery_type_id": 1,
      "transaction_id": "ZaP6V9mU1B1ZXVmpNq4FUP4ypDdZY",
      "square_order_id": null,
      "created_at": "2026-01-14T00:53:43.000000Z",
      "updated_at": "2026-01-14T00:53:43.000000Z",
      "order_items": [
        {
          "id": 55,
          "order_id": 55,
          "product_id": 2,
          "giveaway_id": null,
          "product_name": "Sample Product 2",
          "quantity": "1.00",
          "price": "1000.00",
          "subtotal": "1000.00",
          "created_at": "2026-01-14T00:53:43.000000Z",
          "updated_at": "2026-01-14T00:53:43.000000Z",
          "product": {
            "id": 2,
            "name": "Sample Product 2",
            "price": "1000",
            "quantity": 29969,
            "description": "Description for Sample Product 2"
          }
        }
      ],
      "user": {
        "id": 4,
        "name": "Shakhawat",
        "email": "shakhawatsoftvence@gmail.com",
        "loyalty_points": 18960,
        "referral_code": "JJS-RVJR9WRV"
      },
      "address": {
        "id": 17,
        "label": "Office Address",
        "full_name": "Shakhawat Hossain",
        "phone": "6545865801",
        "email": "shakhawatsoftvence@gmail.com",
        "address": "Mirpur, Dhaka, Bangladesh",
        "zip_code": "1212"
      },
      "delivery_type": {
        "id": 1,
        "name": "Free Delivery",
        "fee": "0.00",
        "days": "1-7 days"
      }
    },
    "payment": {
      "id": 1,
      "order_id": 55,
      "user_id": 4,
      "payment_id": "ZaP6V9mU1B1ZXVmpNq4FUP4ypDdZY",
      "amount": "1000.00",
      "currency": "GBP",
      "status": "completed",
      "payment_method": "square",
      "created_at": "2026-01-14T00:53:43.000000Z",
      "updated_at": "2026-01-14T00:53:43.000000Z"
    },
    "items_count": 1,
    "code": 200
  }
}
```

### Error Response (404)
```json
{
  "success": false,
  "message": "Order not found"
}
```

### Error Response (500)
```json
{
  "success": false,
  "message": "Failed to retrieve payment success data"
}
```

---

## Complete Flow for App Developers

### Step 1: User Places Order
```javascript
// Create order with Square payment token
const response = await fetch('https://your-domain.com/api/orders/newstore', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${authToken}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    shipping_address_id: 17,
    preferred_delivery_date: '17-01-2025',
    notes: 'Optional notes',
    source_id: squareTokenFromSquareSDK
  })
});

const orderData = await response.json();
const orderId = orderData.data.order.id;
```

### Step 2: Get Payment Success Details
```javascript
// Fetch complete payment and order details
const successResponse = await fetch(
  `https://your-domain.com/api/payments/success/${orderId}`,
  {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${authToken}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
  }
);

const successData = await successResponse.json();
console.log(successData.data.message); // "Payment successful! Your order has been placed."
console.log(successData.data.order.order_number); // "JJS00000053"
console.log(successData.data.items_count); // 1
```

### Step 3: Display Success Message
```javascript
if (successData.success) {
  // Show success message
  alert(successData.data.message);
  
  // Display order details
  console.log('Order #' + successData.data.order.order_number);
  console.log('Total: £' + successData.data.order.total);
  console.log('Status: ' + successData.data.order.order_status);
  
  // Redirect to confirmation page
  window.location.href = '/order-confirmation/' + orderId;
}
```

---

## Error Handling

### Common Status Codes
| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad request (validation error) |
| 404 | Resource not found |
| 403 | Unauthorized (not your order) |
| 422 | Unprocessable entity |
| 500 | Server error |

### Example Error Response
```json
{
  "success": false,
  "message": "Cart is empty."
}
```

---

## Important Notes

1. **Authentication Required**: All endpoints require Bearer token
2. **User Security**: Users can only access their own orders
3. **Order Status**: Initial order status is "processing"
4. **Payment Status**: After successful payment, status is "paid"
5. **Loyalty Points**: Automatically calculated and awarded
6. **Email Confirmation**: Sent automatically after order creation

---

## Testing the APIs

### Using cURL
```bash
# Create order
curl -X POST https://your-domain.com/api/orders/newstore \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "shipping_address_id": 17,
    "preferred_delivery_date": "17-01-2025",
    "notes": "Test order"
  }'

# Get payment success details
curl -X GET https://your-domain.com/api/payments/success/55 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Using Postman
1. Set request method to `POST`
2. URL: `https://your-domain.com/api/orders/newstore`
3. Headers tab: Add `Authorization: Bearer YOUR_TOKEN`
4. Body (raw JSON): See request body examples above
5. Click Send

---

## Support
For issues or questions, contact the development team.
