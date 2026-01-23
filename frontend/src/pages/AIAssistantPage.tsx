import React, { useState } from 'react';
import {
  Card,
  Button,
  Input,
  Select,
  Space,
  message,
  Row,
  Col,
  Typography,
  Alert,
  Tag,
  Divider,
  List,
} from 'antd';
import {
  RobotOutlined,
  ThunderboltOutlined,
  BulbOutlined,
  EditOutlined,
  LinkOutlined,
  CheckCircleOutlined,
} from '@ant-design/icons';
import { aiService } from '../services/api';

const { TextArea } = Input;
const { Title, Text, Paragraph } = Typography;

const AIAssistantPage: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<any>(null);

  // Content Generation
  const [topic, setTopic] = useState('');
  const [tone, setTone] = useState('professional');
  const [length, setLength] = useState('500');

  // Summary
  const [content, setContent] = useState('');

  // Keywords
  const [postTitle, setPostTitle] = useState('');
  const [postContent, setPostContent] = useState('');

  const handleGenerateContent = async () => {
    if (!topic) {
      message.warning('Please enter a topic');
      return;
    }

    setLoading(true);
    try {
      const response = await aiService.generateContent({
        topic,
        tone,
        length: parseInt(length),
      });

      if (response.success) {
        setResult({
          type: 'content',
          data: response.text,
        });
        message.success('Content generated successfully!');
      } else {
        message.error(response.error || 'Generation failed');
      }
    } catch (error) {
      message.error('Failed to generate content');
    } finally {
      setLoading(false);
    }
  };

  const handleGenerateSummary = async () => {
    if (!content) {
      message.warning('Please enter content to summarize');
      return;
    }

    setLoading(true);
    try {
      const summary = await aiService.generateSummary(content);
      setResult({
        type: 'summary',
        data: summary,
      });
      message.success('Summary generated!');
    } catch (error) {
      message.error('Failed to generate summary');
    } finally {
      setLoading(false);
    }
  };

  const handleGenerateKeywords = async () => {
    if (!postTitle || !postContent) {
      message.warning('Please enter title and content');
      return;
    }

    setLoading(true);
    try {
      const keywords = await aiService.generateKeywords(postTitle, postContent);
      setResult({
        type: 'keywords',
        data: keywords,
      });
      message.success('Keywords generated!');
    } catch (error) {
      message.error('Failed to generate keywords');
    } finally {
      setLoading(false);
    }
  };

  const handleGenerateIdeas = async () => {
    if (!topic) {
      message.warning('Please enter a topic');
      return;
    }

    setLoading(true);
    try {
      const ideas = await aiService.generateContentIdeas(topic);
      setResult({
        type: 'ideas',
        data: ideas,
      });
      message.success('Content ideas generated!');
    } catch (error) {
      message.error('Failed to generate ideas');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <Title level={2}>
        <RobotOutlined /> AI Assistant
      </Title>
      <Text type="secondary">Generate content, summaries, keywords, and more with AI</Text>

      <Divider />

      <Row gutter={16}>
        {/* Content Generator */}
        <Col span={12}>
          <Card
            title={<Space><BulbOutlined /> Generate Content</Space>}
            extra={<Tag color="blue">GPT-3.5</Tag>}
          >
            <Space direction="vertical" style={{ width: '100%' }} size="large">
              <div>
                <Text strong>Topic:</Text>
                <Input
                  placeholder="Enter blog post topic..."
                  value={topic}
                  onChange={(e) => setTopic(e.target.value)}
                  onPressEnter={handleGenerateContent}
                />
              </div>

              <div>
                <Text strong>Tone:</Text>
                <Select
                  style={{ width: '100%' }}
                  value={tone}
                  onChange={setTone}
                >
                  <Select.Option value="professional">Professional</Select.Option>
                  <Select.Option value="casual">Casual</Select.Option>
                  <Select.Option value="friendly">Friendly</Select.Option>
                  <Select.Option value="formal">Formal</Select.Option>
                  <Select.Option value="humorous">Humorous</Select.Option>
                </Select>
              </div>

              <div>
                <Text strong>Length (words):</Text>
                <Select
                  style={{ width: '100%' }}
                  value={length}
                  onChange={setLength}
                >
                  <Select.Option value="300">Short (~300 words)</Select.Option>
                  <Select.Option value="500">Medium (~500 words)</Select.Option>
                  <Select.Option value="1000">Long (~1000 words)</Select.Option>
                  <Select.Option value="2000">Very Long (~2000 words)</Select.Option>
                </Select>
              </div>

              <Button
                type="primary"
                icon={<ThunderboltOutlined />}
                onClick={handleGenerateContent}
                loading={loading}
                block
              >
                Generate Content
              </Button>
            </Space>
          </Card>
        </Col>

        {/* Summary & Keywords */}
        <Col span={12}>
          <Space direction="vertical" style={{ width: '100%' }} size="large">
            {/* Summary Generator */}
            <Card
              title={<Space><EditOutlined /> Generate Summary</Space>}
              size="small"
            >
              <Space direction="vertical" style={{ width: '100%' }}>
                <TextArea
                  rows={4}
                  placeholder="Paste your content here..."
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                />
                <Button
                  icon={<ThunderboltOutlined />}
                  onClick={handleGenerateSummary}
                  loading={loading}
                  block
                >
                  Generate Summary
                </Button>
              </Space>
            </Card>

            {/* Keywords Generator */}
            <Card
              title={<Space><LinkOutlined /> Generate Keywords</Space>}
              size="small"
            >
              <Space direction="vertical" style={{ width: '100%' }} size="small">
                <Input
                  placeholder="Post title"
                  value={postTitle}
                  onChange={(e) => setPostTitle(e.target.value)}
                />
                <TextArea
                  rows={3}
                  placeholder="Post content (excerpt)"
                  value={postContent}
                  onChange={(e) => setPostContent(e.target.value)}
                />
                <Button
                  icon={<ThunderboltOutlined />}
                  onClick={handleGenerateKeywords}
                  loading={loading}
                  block
                >
                  Generate Keywords
                </Button>
              </Space>
            </Card>

            {/* Content Ideas */}
            <Card
              title={<Space><BulbOutlined /> Content Ideas</Space>}
              size="small"
            >
              <Space.Compact style={{ width: '100%' }}>
                <Input
                  placeholder="Enter topic..."
                  value={topic}
                  onChange={(e) => setTopic(e.target.value)}
                />
                <Button
                  icon={<ThunderboltOutlined />}
                  onClick={handleGenerateIdeas}
                  loading={loading}
                >
                  Generate
                </Button>
              </Space.Compact>
            </Card>
          </Space>
        </Col>
      </Row>

      {/* Result Display */}
      {result && (
        <Card
          title={<Space><CheckCircleOutlined /> Result</Space>}
          style={{ marginTop: 16 }}
          extra={
            <Button
              size="small"
              onClick={() => setResult(null)}
            >
              Clear
            </Button>
          }
        >
          {result.type === 'keywords' ? (
            <Space wrap>
              {result.data.map((keyword: string, i: number) => (
                <Tag key={i} color="blue">{keyword}</Tag>
              ))}
            </Space>
          ) : result.type === 'ideas' ? (
            <List
              dataSource={result.data}
              renderItem={(item: string, i: number) => (
                <List.Item>
                  <Text>
                    <Tag color="green">{i + 1}</Tag> {item}
                  </Text>
                </List.Item>
              )}
            />
          ) : (
            <Paragraph style={{ whiteSpace: 'pre-wrap' }}>
              {result.data}
            </Paragraph>
          )}
        </Card>
      )}

      <Divider />

      <Alert
        message="AI Features"
        description={
          <ul style={{ margin: 0, paddingLeft: 20 }}>
            <li>Generate blog post content from topic</li>
            <li>Create summaries for long content</li>
            <li>Generate SEO keywords</li>
            <li>Brainstorm content ideas</li>
            <li>Proofread and improve text</li>
          </ul>
        }
        type="info"
        showIcon
      />
    </div>
  );
};

export default AIAssistantPage;
