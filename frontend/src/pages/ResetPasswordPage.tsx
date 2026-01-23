import React, { useState, useEffect } from 'react';
import { Form, Input, Button, Card, Typography, message, Divider } from 'antd';
import { LockOutlined, CheckCircleOutlined } from '@ant-design/icons';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { authService } from '../services/api';

const { Title, Text } = Typography;

const ResetPasswordPage: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  const token = searchParams.get('token');
  const email = searchParams.get('email');

  useEffect(() => {
    if (!token || !email) {
      message.error('Ungültiger Link. Bitte fordere einen neuen an.');
      navigate('/forgot-password');
    }
  }, [token, email, navigate]);

  const handleFinish = async (values: { password: string; password_confirmation: string }) => {
    if (!token || !email) return;

    setLoading(true);
    try {
      await authService.resetPassword(email, token, values.password, values.password_confirmation);
      message.success('Passwort erfolgreich geändert! Du kannst dich jetzt einloggen.');
      navigate('/login');
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      message.error(err.response?.data?.message || 'Fehler beim Zurücksetzen des Passworts.');
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
          <CheckCircleOutlined style={{ fontSize: '48px', color: '#667eea', marginBottom: '16px' }} />
          <Title level={2} style={{ color: '#667eea', marginBottom: 8 }}>
            Passwort zurücksetzen
          </Title>
          <Text type="secondary">
            Erstelle ein neues, sicheres Passwort.
          </Text>
        </div>

        <Divider />

        <Form
          name="reset-password"
          onFinish={handleFinish}
          layout="vertical"
        >
          <Form.Item
            label="Neues Passwort"
            name="password"
            rules={[
              { required: true, message: 'Bitte Passwort eingeben' },
              { min: 12, message: 'Mindestens 12 Zeichen' },
            ]}
          >
            <Input.Password
              prefix={<LockOutlined />}
              placeholder="Neues Passwort"
              size="large"
            />
          </Form.Item>

          <Form.Item
            label="Passwort bestätigen"
            name="password_confirmation"
            dependencies={['password']}
            rules={[
              { required: true, message: 'Bitte bestätigen' },
              ({ getFieldValue }) => ({
                validator(_, value) {
                  if (!value || getFieldValue('password') === value) {
                    return Promise.resolve();
                  }
                  return Promise.reject(new Error('Passwörter stimmen nicht überein!'));
                },
              }),
            ]}
          >
            <Input.Password
              prefix={<LockOutlined />}
              placeholder="Passwort bestätigen"
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
              Passwort ändern
            </Button>
          </Form.Item>
        </Form>
      </Card>
    </div>
  );
};

export default ResetPasswordPage;
