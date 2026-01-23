import React, { useState } from 'react';
import { Form, Input, Button, Card, Typography, message, Divider, Space } from 'antd';
import { MailOutlined, KeyOutlined, ArrowLeftOutlined } from '@ant-design/icons';
import { useNavigate, Link } from 'react-router-dom';
import { authService } from '../services/api';

const { Title, Text } = Typography;

const ForgotPasswordPage: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleFinish = async (values: { email: string }) => {
    setLoading(true);
    try {
      await authService.requestPasswordReset(values.email);
      message.success('Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen gesendet.');
      navigate('/login');
    } catch (error: unknown) {
      // Security: Always show success message or generic error to prevent email enumeration
      // But for debugging/admin we might show actual error if configured
      message.success('Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen gesendet.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      minHeight: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
      padding: '20px'
    }}>
      <Card
        style={{
          width: '100%',
          maxWidth: 400,
          borderRadius: '12px',
          boxShadow: '0 8px 32px rgba(0,0,0,0.1)'
        }}
      >
        <div style={{ textAlign: 'center', marginBottom: 32 }}>
          <KeyOutlined style={{ fontSize: '48px', color: '#667eea', marginBottom: '16px' }} />
          <Title level={2} style={{ color: '#667eea', marginBottom: 8 }}>
            Passwort vergessen?
          </Title>
          <Text type="secondary">
            Gib deine E-Mail-Adresse ein, um dein Passwort zurückzusetzen.
          </Text>
        </div>

        <Divider />

        <Form
          name="forgot-password"
          onFinish={handleFinish}
          layout="vertical"
        >
          <Form.Item
            label="Email"
            name="email"
            rules={[
              { required: true, message: 'Bitte Email eingeben' },
              { type: 'email', message: 'Ungültige Email' },
            ]}
          >
            <Input
              prefix={<MailOutlined />}
              placeholder="deine@email.com"
              size="large"
            />
          </Form.Item>

          <Form.Item>
            <Button
              type="primary"
              htmlType="submit"
              loading={loading}
              size="large"
              block
            >
              Reset Link senden
            </Button>
          </Form.Item>

          <div style={{ textAlign: 'center' }}>
            <Link to="/login">
              <Space>
                <ArrowLeftOutlined /> Zurück zum Login
              </Space>
            </Link>
          </div>
        </Form>
      </Card>
    </div>
  );
};

export default ForgotPasswordPage;
